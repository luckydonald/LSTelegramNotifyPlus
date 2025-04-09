<?php

class LSTestPlugin extends PluginBase
{
    public function init()
    {
        // Register your JavaScript
        Yii::app()->clientScript->registerScript(static::class, $this->getJavaScript(), CClientScript::POS_END);
    }
    private function getJavaScriptName($fn = 'testConfiguration') {
        $cls = static::class;
        return "{$cls}_{$fn}";
    }

    public function getConfig()
    {
        $jsFuncName = $this->getJavaScriptName();
        // Add your configuration fields here
        return [
            'testButton' => [
                'type' => 'button',
                'label' => 'Test Configuration',
                'onclick' => "{$jsFuncName}()",
            ],
            // Other configuration fields...
        ];
    }

    public function getJavaScript()
    {
        $cls = static::class;
        $url = Yii::app()->createUrl('admin/plugin/'.$cls);
        $func = $this->getJavaScriptName();
        return "
        <script>
            function {$func}() {
                // Make an AJAX call to the testConfiguration method
                $.ajax({
                    url: '{$url}',
                    type: 'POST',
                    success: function(response) {
                        alert(response); // Show the response message
                    },
                    error: function() {
                        alert('An error occurred while testing the configuration.');
                    }
                });
            }
        </script>
    ";
    }

    public function testConfiguration()
    {
        // Logic to test the configuration
        // For example, you might want to test a database connection or an API call
        // Return a success or error message
        return 'Test successful!'; // or return an error message
    }
}
