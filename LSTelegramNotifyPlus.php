<?php

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;

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
     * @noinspection HtmlUnknownTarget
     * @noinspection PhpIdempotentOperationInspection
     */
    protected $settings = [
        'Enable' => [
            'type' => 'checkbox',
            'label' => 'Enable telegram notifications',
            'default' => true,
        ],
        'SettingsInfo' => [
            'type' => 'info',
            'content' => '<legend><small>Telegram settings</small></legend>'
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
        'SettingsInfo2' => [
            'type' => 'info',
            'content' => '<legend><small>Text message settings</small></legend>'
        ],
        'SendMessage' => [
            'type' => 'checkbox',
            'label' => 'Check to send a text message using the default text template',
        ],
        'ParseMode' => [
            'type' => 'select',
            'label' => 'Parse mode',
            'options' => array('HTML' => 'HTML', 'Markdown'  => 'Markdown', 'MarkdownV2' => 'MarkdownV2', 'Text' => 'Text'),
            'help' => 'As the Telegram bot API <a href="https://core.telegram.org/bots/api#formatting-options" target="_blank">formatting options</a>.',
            'default' => 'HTML',
        ],
        'Template' => [
            'type' => 'text',
            'label' => 'Template Text',
            'default' =>
                "<b>New <a href=\"{urlSurvey}\">{title}</a> Survey Completion!</b>\n" .
                "SurveyId: <code>{surveyId}</code>\n" .
                "ResponseId: <code>{responseId}</code>\n" .
                "<a href=\"{urlDetails}\">View</a>" .
                " | <a href=\"{urlEdit}\">Edit</a>" .
                " | <a href=\"{urlPDF}\">PDF</a>" .
                " | <a href=\"{urlExport}\">Export</a>" .
                " | <a href=\"{urlAttachments}\">Attachments</a>" .
                "\n" .
                "",
            '{replacements}' => [
                '{title}' => 'Title of the survey.',
                '{surveyId}' => 'ID of the survey.',
                '{responseId}' => 'ID of the survey response.',
                '{urlSurvey}' => "Url to the details page of the survey.",
                '{urlDetails}' => "Url to the details page of the survey response.",
                '{urlEdit}' => "Url to the edit page of the survey response.",
                '{urlPDF}' => "Url to the pdf download of the survey response.",
                '{urlExport}' => "Url to the export page of survey response.",
                '{urlAttachments}' => "Url to the attachments of the survey response.",
            ],
            '{help}' =>
                '<br>The default is:' .
                '<br><pre>{default}</pre>' .
                '<br>The default would render like:' .
                '<br><p class="alert alert-secondary">{example}</p>' .
                '<br>Available replacements are:' .
                '<br><ul>{replacements}</ul>' .
                ''
            ,
        ],
        'SettingsInfo3' => [
            'type' => 'info',
            'content' => '<legend><small>Attachment message settings</small></legend>'
        ],
        'Reply' => [
            'type' => 'checkbox',
            'label' => 'Reply file messages to the text message.',
            'default' => true,
        ],
        'SendPdf' => [
            'type' => 'checkbox',
            'label' => 'Check to send the answer as PDF file',
        ],
        'SendCsv' => [
            'type' => 'checkbox',
            'label' => 'Check to send all answers as CSV file',
        ],
        'SendAttachments' => [
            'type' => 'checkbox',
            'label' => 'Check to send all attachments uploaded',
        ],
    ];

    public function __construct(LimeSurvey\PluginManager\PluginManager $manager, $id)
    {
        $this->settings['Template']['help'] = $this->replaceTemplateHelp();
        parent::__construct($manager, $id);
    }

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
        $enable = $this->getSurveySettings('Enable', $surveyId);
        if (!$enable) {
            return;
        }
        $responseId = $event->get('responseId');
        $oSurvey = Survey::model()->findByPk($surveyId);
        if (!$oSurvey) {
            return;
        }
        $baseUrl = $this->getSurveySettings('BaseUrl', $surveyId);
        $authToken = $this->getSurveySettings('AuthToken', $surveyId);
        $chatId = $this->getSurveySettings('ChatId', $surveyId);

        // Create a Guzzle client
        $client = new GuzzleClient([
            'base_uri' => rtrim($baseUrl, '/').'/bot'.$authToken.'/'
        ]);

        $messageId = $this->sendMessage($surveyId, $responseId, $chatId, $client, $oSurvey->getLocalizedTitle());
        $this->sendPdf($surveyId, $responseId, $chatId, $client, $messageId);
        $this->sendCsv($surveyId, $responseId, $chatId, $client, $messageId);
        $this->sendAttachments($surveyId, $responseId, $chatId, $client, $messageId);
    }
    
    public function getSurveySettings(string $key, $surveyId = null, $default = null) {
        $globalValue = $this->get($key, null, null, $default);
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
    public function sendMessage($surveyId, $responseId, $chatId, GuzzleClient $telegram, $title): ?int
    {
        $sendMessage = $this->getSurveySettings('SendMessage', $surveyId);
        if (!$sendMessage) {
            return null;
        }
        $template = $this->getSurveySettings('Template', $surveyId);
        $parseMode = $this->getSurveySettings('ParseMode', $surveyId);
        $text = $this->replaceTemplate($surveyId, $responseId, $title, $template, $parseMode);

        $tgResponse = $this->sendTelegram(
            $telegram,
            $chatId,
            null,
            'sendMessage',
            [
                'parse_mode' => $parseMode,
                'text' => $text,
            ]
        );
        $reply = $this->getSurveySettings('Reply', $surveyId);
        if (!$reply) {
            // if reply is disabled, simply don't provide that information.
            return null;
        }
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
        try {
            $response = $client->post($command, $args);
            // Process the response if needed
        } catch (BadResponseException $e) {
            if ($e->hasResponse()) {
                $r = $e->getResponse();
                $error = "Could not {$command} ({$r->getStatusCode()}): {$r->getBody()}";
            } else {
                $error = "Could not {$command}.";
            }
            $error .= 'Please inform the administrator and try again later.';
            Yii::log(
                (
                    "LSTNP: Failed to do telegram command {$command}:\n"
                    ."{$error}\n"
                    ."{$e->getTraceAsString()}"
                ),
                CLogger::LEVEL_ERROR
            );
            throw new \Exception('LSTNP: ' . $error);
        } catch (\Exception $e) {
            // Display a user-friendly message
            $error = 'An unexpected error occurred. Please inform the administrator and try again later.';
            Yii::log(
                (
                    "LSTNP: Failed to do telegram command {$command} with unexpected exception type:\n"
                    ."{$error}\n"
                    ."{$e->getTraceAsString()}"
                ),
                CLogger::LEVEL_ERROR
            );
            throw new \Exception('LSTNP: ' . $error);
        }
        return json_decode($response->getBody(), true);
    }

    private function sendPdf($surveyId, $responseId, $chatId, GuzzleClient $telegram, $messageId): void
    {
        $sendPdf = $this->getSurveySettings('SendPdf', $surveyId);
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

    private function sendCsv($surveyId, $responseId, $chatId, GuzzleClient $telegram, $messageId): void
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

    private function sendAttachments($surveyId, $responseId, $chatId, GuzzleClient $telegram, $messageId): void
    {
        $sendAttachments = $this->getSurveySettings('SendAttachments', $surveyId);
        if (!$sendAttachments) {
            return;
        }
        $response = Response::model($surveyId)->findByAttributes([
            'id' => $this->getEvent()->get('responseId')
        ]);
        if (!$response) {
            return;
        }
        $response = $response->decrypt();
        $keys = [];
        $files = [];
        $datas = [];
        $i = 0;
        foreach ($response->getFiles() as $aFile) {
            $key = "attachment_{$i}";
            $i += 1;
            $filepath = Yii::app()->getConfig('uploaddir') . "/surveys/" . $surveyId . "/files/" . $aFile['filename'];
            $filename = "{$surveyId}-{$responseId}_{$aFile['filename']}_{$aFile['name']}";
            $file = [$filepath, $filename];
            $title = '';
            if ($aFile['title'] ?? null) {
                $title = htmlspecialchars($aFile['title']);
                $title = "<b>{$title}</b>";
            }
            $comment = '';
            if ($aFile['comment'] ?? null) {
                $comment = htmlspecialchars($aFile['comment']);
            }
            $caption = "$title\n$comment";
            $caption = trim($caption);

            $inputMediaDocument = [
                'type' => 'document',
                'media' => "attach://{$key}",
                'parse_mode' => 'html'
            ];
            if ($caption) {
                $inputMediaDocument['caption'] = $caption;
            }
            $keys[] = $key;
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
        $surveyId = $event->get('survey');

        $oSurvey = Survey::model()->findByPk($surveyId);
        $title = $oSurvey->title ?? 'Cool Survey';

        $currents = [
            'Enable' => $this->getSurveySettings('Enable', $surveyId, $this->settings['Enable']['default']),
            'BaseUrl' => $this->getSurveySettings('BaseUrl', $surveyId),
            'AuthToken' => $this->getSurveySettings('AuthToken', $surveyId),
            'ChatId' => $this->getSurveySettings('ChatId', $surveyId),
            'SendMessage' => $this->getSurveySettings('SendMessage', $surveyId, $this->settings['SendMessage']['default']),
            'ParseMode' => $this->getSurveySettings('ParseMode', $surveyId),
            'Template' => $this->getSurveySettings('Template', $surveyId, $this->settings['Template']['default']),
            'Reply' => $this->getSurveySettings('Reply', $surveyId, $this->settings['Reply']['default']),
            'SendPdf' => $this->getSurveySettings('SendPdf', $surveyId, $this->settings['SendPdf']['default']),
            'SendCsv' => $this->getSurveySettings('SendCsv', $surveyId, $this->settings['SendCsv']['default']),
            'SendAttachments' => $this->getSurveySettings('SendAttachments', $surveyId, $this->settings['SendAttachments']['default']),
        ];

        $settings = [];
        foreach ($this->settings as $name => $value) {
            if (!isset($currents[$name])) {
                $settings[$name] = $value;
            } else {
                $settings[$name] = array_merge(
                    ['current' => $currents[$name]],
                    $value
                );
            }
        }
        $settings['Template']['help'] = $this->replaceTemplateHelp($surveyId, 1, $title);
        $surveysettings = [
            'name' => get_class($this),
            'settings' => $settings,
        ];
        $event->set("surveysettings.{$this->id}", $surveysettings);
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

    /**
     * @param $surveyId
     * @param $responseId
     * @param $title
     * @param $defaultText
     * @return array
     */
    public function replaceTemplate($surveyId, $responseId, $title, $defaultText, $parseMode): string
    {
        $pdfUrl = App()->createAbsoluteUrl(
            '/responses/viewquexmlpdf',
            [
                'surveyId' => $surveyId,
                'id' => $responseId,
            ]
        );
        $surveyUrl = App()->createAbsoluteUrl(
            "/surveyAdministration/view",
            [
                'surveyid' => $surveyId,
            ]
        );
        $detailsUrl = App()->createAbsoluteUrl(
            '/responses/view',
            [
                'surveyId' => $surveyId,
                'id' => $responseId
            ]
        );
        $editUrl = App()->createAbsoluteUrl(
            "/admin/dataentry/sa/editdata/subaction/edit/surveyId/$surveyId/id/$responseId/browseLang"
        );
        $exportUrl = App()->createAbsoluteUrl(
            "/admin/export/sa/exportresults/surveyid/$surveyId/id/$responseId"
        );
        $attachmentsUrl = App()->createAbsoluteUrl(
            "/responses/downloadfiles",
            [
                'surveyId' => $surveyId,
                'responseIds' => $responseId
            ]
        );
        $replacements = [
            '/\{title\}/' => $title,
            '/\{surveyId\}/' => $surveyId,
            '/\{responseId\}/' => $responseId,
            '/\{urlPDF\}/' => $pdfUrl,
            '/\{urlSurvey\}/' => $surveyUrl,
            '/\{urlDetails\}/' => $detailsUrl,
            '/\{urlEdit\}/' => $editUrl,
            '/\{urlExport\}/' => $exportUrl,
            '/\{urlAttachments\}/' => $attachmentsUrl,
        ];
        if (strtolower($parseMode ?? '') === 'html') {
            // make sure the stuff is escaped.
            foreach ($replacements as $key => $value) {
                $replacements[$key] = htmlspecialchars($value, ENT_QUOTES);
            }
        }
        return preg_replace(
            array_keys($replacements),
            array_values($replacements),
            $defaultText
        );
    }

    /**
     * @return array|string|string[]|null
     */
    public function replaceTemplateHelp(
        $surveyId=123456,
        $responseId=1,
        $title='Not A Real Survey',
        $parseMode='html'
    ) {
        $info = $this->settings['Template']['{replacements}'];
        $template = $this->settings['Template']['{help}'];

        $html = $this->replaceTemplate(
            $surveyId,
            $responseId,
            $title,
            $this->settings['Template']['default'],
            $parseMode
        );
        $replacements = [
            '/\{default\}/' => htmlspecialchars($this->settings['Template']['default']),
            '/\{example\}/' => $html,
            '/\{replacements\}/' => join(
                "\n",
                array_map(
                    function ($key, $value) {
                        return "<li><code>{$key}</code>: <i>$value</i></li>";
                    },
                    array_keys($info),
                    array_values($info)
                )
            ),
            "/\n/" => "<br/>\n",
        ];
        return preg_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }
}
