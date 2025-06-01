# Lingo WP Translator

WordPress plugin to perform automatic translations by integrating [Lingo.dev PHP SDK](https://github.com/lingodotdev/sdk-php)

## Installation and Setup

To install this plugin, simply navigate to your WordPress plugin directory (wordpress/wp-content/plugins by default) and clone this repo into it.

Alternatively, you can download this repo as a zip file and upload the extracted content to the same location as above.

After completing the above step, navigate to the folder and install the [Lingo.dev PHP SDK](https://github.com/lingodotdev/sdk-php) with Composer:

```bash
$ cd lingo-wp-translator

$ composer require lingodotdev/sdk
```

## Using the plugin

1. After installation, navigate to the plugin section in your WordPress admin panel to find `Lingo WP Translator` and activate it.

2. In the WP admin, navigate to **settings>Lingo Translation Test**

3. Add your [Lingo.dev](https://lingo.dev/) API key, add a source language and the target languages you wish to translate to. You can optionally select the `fast mode` to make translations faster, at the expense of accuracy.

4. Find the **Test String Translation** in the **Lingo Translation Test** settings page to test if your configuration works. Simply put in your desired text, select a target language and click on **Translate String**
