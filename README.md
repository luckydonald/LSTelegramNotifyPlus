# LimeSurvey LSTelegramNotifyPlus Plugin
#### Notify form submission in Telegram, as PDF or CSV and with attachments

<img src="img/telegram_example.png" alt="chat example" />

Added features:
- works with limesurvey 6.x, too
- enable/disable per survey
- post attachments (optional)
- reply to chain (optional)
- escape html in parse mode html templates

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
  - There's both the global settings and per survey settings
  - survey settings will copy global settings, after you save them once, they will be independent.

<img src="img/settings.png"  alt="settings"/>

### Custom settings by survey
You can add custom settings by survey to send the messages to other groups, customize the text and change other settings.

- Go to survey settings
- GO to `Simple plugins`
- Define your custom settings at `Settings for plugin LSTelegramNotifyPlus `
