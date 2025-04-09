<?php

use GuzzleHttp\Client;
use Telegram\Bot\FileUpload\InputFile;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

/**
 * Class LSTelegramNotifyPlus
 */
class LSTelegramNotifyPlus extends PluginBase
{
    /**
     * @var string
     */
    static protected $description = 'LSTelegramNotifyPlus Plugin';

    /**
     * @var string
     */
    static protected $name = 'LSTelegramNotifyPlus';

    /**
     * @var string
     */
    protected $storage = 'DbStorage';

    /**
     * @var string[][]
     */
    protected $settings = [
        'Enable' => [
            'type' => 'checkbox',
            'label' => 'Enable telegram notifications',
        ],
        'AuthToken' => [
            'type' => 'string',
            'label' => 'Auth Token',
            'help' => 'Bot API auth token, you can get one at <a href="https://t.me/BotFather" target="_blank">BotFather</a>.',
        ],
        'BaseUrl' => [
            'type' => 'string',
            'label' => 'Base URL',
            'help' => 'URL of your base URL.',
            'default' => 'https://api.telegram.org/',
        ],
        'ChatId' => [
            'type' => 'string',
            'label' => 'Chat id',
            'help' => 'The ID of group that will receive the notification messages. You can add the bot <a href="https://t.me/RawDataBot" target="_blank">RawDataBot</a> to your group, get the chat_id and after remove this bot from group.',
        ],
        'ParseMode' => [
            'type' => 'select',
            'label' => 'Parse mode',
            'options' => array('HTML' => 'HTML', 'Markdown'  => 'Markdown', 'MarkdownV2' => 'MarkdownV2'),
            'help' => 'As the Telegram bot API <a href="https://core.telegram.org/bots/api#formatting-options" target="_blank">formatting options</a>.',
            'default' => 'HTML',
        ],
        'SendPdf' => [
            'type' => 'checkbox',
            'label' => 'Check to send the answer as PDF file',
        ],
        'SendCsv' => [
            'type' => 'checkbox',
            'label' => 'Check to send all answers as CSV file',
        ],
        'SendMessage' => [
            'type' => 'checkbox',
            'label' => 'Check to send a text message using the default text template',
        ],
        'SendAttachments' => [
            'type' => 'checkbox',
            'label' => 'Check to send all attachments uploaded',
        ],
        'DefaultText' => [
            'type' => 'text',
            'label' => 'Default Text',
            'default' =>
                "New Survey Completed!\n" .
                "Title: <code>{title}</code>\n" .
                "SurveyId: <code>{surveyId}</code>\n" .
                "ResponseId: <code>{responseId}</code>\n" .
                "PDF: <a href=\"{urlPDF}\">here</a>"
        ],
    ];

    /**
     * @return void
     */
    public function init()
    {
        $this->subscribe('newSurveySettings');
        $this->subscribe('afterSurveyComplete');
        $this->subscribe('beforeSurveySettings');
    }

    /**
     * @return void
     */
    public function afterSurveyComplete()
    {
        $event = $this->getEvent();
        $surveyId = $event->get('surveyId');
        $baseUrl = $this->get(
            'Enabled', // local
            'Survey',
            $surveyId, // Survey
            $this->get('Enabled') // Global
        );
        $responseId = $event->get('responseId');
        $oSurvey = Survey::model()->findByPk($surveyId);
        $baseUrl = $this->getSurveySettings('BaseUrl', $surveyId);
        $authToken = $this->getSurveySettings('AuthToken', $surveyId);
        $chatId = $this->getSurveySettings('ChatId', $surveyId);

        // Create a Guzzle client
        $client = new Client([
            'base_uri' => $baseUrl.'/bot'.$authToken.'/',
        ]);
        $messageId = $this->sendMessage($surveyId, $responseId, $chatId, $client, $oSurvey->getLocalizedTitle());
        $this->sendPdf($surveyId, $responseId, $chatId, $client, $messageId);
        $this->sendCsv($surveyId, $responseId, $chatId, $client, $messageId);
        $this->sendAttachments($surveyId, $responseId, $chatId, $client, $messageId);
    }
    
    public function getSurveySettings(string $key, $surveyId = null) {
        $globalValue = $this->get($key);
        $surveyId = $surveyId ?? $this->getEvent()->get('surveyId');
        return $this->get($key, 'Survey', $surveyId, $globalValue);
    }

    /**
     * Send the message on Telegram
     *
     * @param $surveyId
     * @param $text
     * @return int Message ID
     */
    public function sendMessage($surveyId, $responseId, $chatId, Client $telegram, $title)
    {
        $sendMessage = $this->getSurveySettings('SendMessage', $surveyId);
        if (!$sendMessage) {
            return;
        }
        $pdfUrl = App()->createAbsoluteUrl(
            '/admin/responses/sa/viewquexmlpdf',
            [
                'surveyid' => $surveyId,
                'id' => $responseId
            ]
        );
        $replacements = [
            '/\{surveyId\}/' => $surveyId,
            '/\{responseId\}/' => $responseId,
            '/\{urlPDF\}/' => $pdfUrl,
            '/\{title\}/' => $title,
        ];
        $defaultText = $this->getSurveySettings('DefaultText', $surveyId);
        $text = preg_replace(
            array_keys($replacements),
            array_values($replacements),
            $defaultText
        );
        $parseMode = $this->getSurveySettings('ParseMode', $surveyId);
        $tgResponse = $this->sendTelegram(
            $telegram,
            $chatId,
            null,
            [
                'parse_mode' => $parseMode,
                'text' => $text,
            ]
        );
        return $tgResponse['result']['message_id'];
    }
    private function sendTelegram(
        $client,
        $chatId,
        $replyToMessageId,
        $command="sendMessage",
        $params=[],
        $attachments=[]
    ) {
        $buildParams = [
            'chat_id' => $chatId,
        ];
        // If there's a reply to a message, add the reply_to_message_id parameter
        if ($replyToMessageId) {
            $buildParams['reply_parameters'] = [
                'allow_sending_without_reply' => true,
                'message_id' => $replyToMessageId,
            ];
        }

        $buildParams = array_merge(
            $buildParams,
            $params
        );

        if (count($attachments) == 0) {
            $args = [
                'form_params' => $buildParams
            ];
        } else {
            $args = [
                'multipart' => array_merge(
                    array_map(
                        function ($key, $value) {
                            return [
                                'name'     => $key,
                                'contents' => is_array($value) ? json_encode($value) : $value
                            ];
                        },
                        array_keys($buildParams),
                        array_values($buildParams)
                    ),
                    array_map(
                        function ($key, $value) {
                            if (!is_array($value)) {
                                $value = [$value, basename($value)];
                            }
                            return [
                                'name'     => $key,
                                'contents' => fopen($value[0], 'r'),
                                'filename' => $value[1]
                            ];
                        },
                        array_keys($attachments),
                        array_values($attachments)
                    )
                )
            ];
        }

        // Send the request
        $response = $client->post($command, $args);

        return json_decode($response->getBody(), true);
    }

    private function sendPdf($surveyId, $responseId, $chatId, Client $telegram, $messageId): void
    {
        $sendPdf = $this->get(
            'SendPdf',
            'Survey',
            $surveyId, // Survey
            $this->get('SendPdf') // Global
        );
        if (!$sendPdf) {
            return;
        }
        $pdfPath = $this->getPdfPath($surveyId, $responseId);
        $this->sendTelegram(
            $telegram,
            $chatId,
            $messageId,
            'sendDocument',
            [],
            [
                'document' => [$pdfPath, "$surveyId-$responseId.pdf"]
            ]
        );
        unlink($pdfPath);
    }

    private function sendCsv($surveyId, $responseId, $chatId, Client $telegram, $messageId): void
    {
        $sendCsv = $this->getSurveySettings('SendCsv', $surveyId);
        if (!$sendCsv) {
            return;
        }
        $pdfPath = $this->getCsv($surveyId, $responseId);
        $this->sendTelegram(
            $telegram,
            $chatId,
            $messageId,
            'sendDocument',
            [],
            [
                'document' => [$pdfPath, "$surveyId-$responseId.csv"]
            ]
        );
        unlink($pdfPath);
    }

    private function sendAttachments($surveyId, $responseId, $chatId, Client $telegram, $messageId): void
    {
        $sendAttachments = $this->getSurveySettings('SendAttachments', $surveyId);
        if (!$sendAttachments) {
            return;
        }
        $response = Response::model($surveyId)->findByAttributes([
            'id' => $this->getEvent()->get('responseId')
        ])->decrypt();
        $keys = [];
        $files = [];
        $datas = [];
        $i = 0;
        foreach ($response->getFiles() as $aFile) {
            $key = "attachment_{$i}";
            $i += 1;
            $filepath = Yii::app()->getConfig('uploaddir') . "/surveys/" . $surveyId . "/files/" . $aFile['filename'];
            $filename = "{$surveyId}-{$responseId}_{$aFile['name']}_{$aFile['filename']}";
            $file = [$filepath, $filename];
            $inputMediaDocument = [
                'type' => 'document',
                'media' => "attach://{$key}",
                'caption' => $aFile['title'] ?? $aFile['comment'] ?? null, // Optional caption
            ];
            $files[$key] = $file;
            $datas[$key] = $inputMediaDocument;
        }
        for ($i = 0; $i < count($datas); $i += 10) {
            $upTo10Keys = array_slice($keys, $i, 10);
            $upTo10Files = array_intersect_key($files, array_flip($upTo10Keys));  // array_flip makes the value the key
            $upTo10Datas = array_values(array_intersect_key($datas, array_flip($upTo10Keys)));
            $this->sendTelegram(
                $telegram,
                $chatId,
                $messageId,
                'sendMediaGroup',
                [
                    'media' => json_encode($upTo10Datas),
                ],
                $upTo10Files
            );
        }
    }

    private function getPdfPath($surveyId, $responseId): string
    {
        Yii::import("application.libraries.admin.quexmlpdf", true);
        $oSurvey = Survey::model()->findByPk($surveyId);
        $quexmlpdf = new quexmlpdf();
        set_time_limit(120);
        App()->loadHelper('export');
        $quexml = quexml_export($surveyId, current($oSurvey->allLanguages), $responseId);
        $quexmlpdf->create($quexmlpdf->createqueXML($quexml));

        $tempnam = tempnam(sys_get_temp_dir(), 'pdf_');

        $quexmlpdf->Output($tempnam, 'F');
        return $tempnam;
    }

    /**
     * @return void
     */
    public function beforeSurveySettings()
    {
        $event = $this->getEvent();
        $event->set(
            "surveysettings.{$this->id}",
            [
                'name' => get_class($this),
                'settings' => [
                    'SettingsInfo' => [
                        'type' => 'info',
                        'content' => '<legend><small>Telegram settings</small></legend>'
                    ],
                    'BaseUrl' => [
                        'type' => 'string',
                        'label' => $this->settings['BaseUrl']['help'],
                        'help' => $this->settings['BaseUrl']['help'],
                        'default' => $this->settings['BaseUrl']['default'],
                        'current' => $this->get(
                            'BaseUrl',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('BaseUrl') // Global
                        ),
                    ],
                    'AuthToken' => [
                        'type' => 'string',
                        'label' => $this->settings['AuthToken']['help'],
                        'help' => $this->settings['AuthToken']['help'],
                        'current' => $this->get(
                            'AuthToken',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('AuthToken') // Global
                        ),
                    ],
                    'ChatId' => [
                        'type' => 'string',
                        'label' => 'Chat id',
                        'help' => $this->settings['ChatId']['help'],
                        'current' => $this->get(
                            'ChatId',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get('ChatId') // Global
                        ),
                    ],
                    'ParseMode' => [
                        'type' => $this->settings['ParseMode']['type'],
                        'label' => $this->settings['ParseMode']['label'],
                        'options' => $this->settings['ParseMode']['options'],
                        'help' => $this->settings['ParseMode']['help'],
                        'default' => $this->settings['ParseMode']['default'],
                        'current' => $this->get(
                            'ParseMode',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'ParseMode',
                                null,
                                null,
                                $this->settings['ParseMode']['default']
                            ) // Global
                        ),
                    ],
                    'SendPdf' => [
                        'type' => $this->settings['SendPdf']['type'],
                        'label' => $this->settings['SendPdf']['label'],
                        'current' => $this->get(
                            'SendPdf',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'SendPdf',
                                null,
                                null,
                                $this->settings['SendPdf']['default']
                            ) // Global
                        ),
                    ],
                    'SendCsv' => [
                        'type' => $this->settings['SendCsv']['type'],
                        'label' => $this->settings['SendCsv']['label'],
                        'current' => $this->get(
                            'SendCsv',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'SendCsv',
                                null,
                                null,
                                $this->settings['SendCsv']['default']
                            ) // Global
                        ),
                    ],
                    'SendMessage' => [
                        'type' => $this->settings['SendMessage']['type'],
                        'label' => $this->settings['SendMessage']['label'],
                        'current' => $this->get(
                            'SendMessage',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'SendMessage',
                                null,
                                null,
                                $this->settings['SendMessage']['default']
                            ) // Global
                        ),
                    ],
                    'SendAttachments' => [
                        'type' => $this->settings['SendAttachments']['type'],
                        'label' => $this->settings['SendAttachments']['label'],
                        'current' => $this->get(
                            'SendAttachments',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'SendAttachments',
                                null,
                                null,
                                $this->settings['SendAttachments']['default']
                            ) // Global
                        ),
                    ],
                    'DefaultText' => [
                        'type' => 'text',
                        'label' => 'Default Text',
                        'current' => $this->get(
                            'DefaultText',
                            'Survey',
                            $event->get('survey'), // Survey
                            $this->get(
                                'DefaultText',
                                null,
                                null,
                                $this->settings['DefaultText']['default']
                            ) // Global
                        ),
                    ]
                ]
            ]
        );
    }

    /**
     * @return void
     */
    public function newSurveySettings()
    {
        $event = $this->getEvent();

        foreach ($event->get('settings') as $name => $value) {
            $this->set($name, $value, 'Survey', $event->get('survey'));
        }
    }

    /**
     * Save file to CSV
     *
     * @return string File path
     */
    private function getCsv($surveyId, $responseId): string
    {
        Yii::import('application.helpers.admin.export.FormattingOptions', true);
        Yii::import('application.helpers.admin.exportresults_helper', true);
        $survey = Survey::model()->findByPk($surveyId);
        if (!($maxId = SurveyDynamic::model($surveyId)->getMaxId())) {
            throw new Exception('No Data, could not get max id.', 1);
        }
        $oFormattingOptions = new FormattingOptions();
        $oFormattingOptions->responseMinRecord = 1;
        $oFormattingOptions->responseMaxRecord = $maxId;
        $aFields = array_keys(createFieldMap($survey, 'full', true, false, $survey->language));
        $aTokenFields = array('tid','participant_id','firstname','lastname','email','emailstatus','language','blacklisted','sent','remindersent','remindercount','completed','usesleft','validfrom','validuntil','mpid');
        $oFormattingOptions->selectedColumns = array_merge($aFields,$aTokenFields, array_keys($survey->tokenAttributes));
        $oFormattingOptions->responseCompletionState = 'all';
        $oFormattingOptions->headingFormat = 'full';
        $oFormattingOptions->answerFormat = 'long';
        $oFormattingOptions->csvFieldSeparator = ',';
        $oFormattingOptions->output = 'file';
        $oExport = new ExportSurveyResultsService();
        $tempFile = $oExport->exportResponses($surveyId, $survey->language, 'csv', $oFormattingOptions, '');
        return $tempFile;
    }
}
