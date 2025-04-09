# LimeSurvey LSTelegramNotifyPlus Plugin
#### Notify form submission in Telegram, as PDF or CSV and with attachments

<img src="img/telegram_example.png" />

## Plugin Installation

### By zipfile

- Download the zip of [latest release](https://github.com/luckydonald/LSTelegramPlus/releases/latest)
- Install the plugin in `Settings` > `Plugin Manager` > `Install zip`

### By repository
- Download the zip of [source code](https://github.com/luckydonald/LSTelegramNotifyPlus/archive/refs/heads/mane.zip)
- Copy the LSTelegramNotifyPlus folder to the Limesurvey "plugins" directory.
- Go to `LSTelegramNotifyPlus` folder
- Run `composer install` inside of folder `LSTelegramNotifyPlus`
- Activate the plugin at the Limesurvey plugin manager (requires proper user rights for accessing the feature at the Limesurvey admin interface).
- Configure the plugin at the settings page

<img src="img/settings.png" />

### Custom settings by survey
You can add custom settings by survey to send the messages to other groups, customize the text and change other settings.

- Go to survey settings
- GO to `Simple plugins`
- Define your custom settings at `Settings for plugin LSTelegramNotifyPlus `
