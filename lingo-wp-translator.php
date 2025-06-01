<?php
/**
 * Plugin Name:     Lingo WP Translator
 * Plugin URI:      https://github.com/ade555/lingo-wp-translator
 * Description:     Simplified Lingo.dev AI translation for testing strings in WordPress admin.
 * Version:         1.0.0
 * Author:          Ademola Thompson
 * License:         MIT
 * Text Domain:     lingo-wp-translator-test
 * Domain Path:     /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('LINGO_PLUGIN_VERSION', '1.0.0');
define('LINGO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('LINGO_PLUGIN_URL', plugin_dir_url(__FILE__));


require_once __DIR__ . '/vendor/autoload.php';
use LingoDotDev\Sdk\LingoDotDevEngine;

/**
 * Main Lingo Localization Plugin Class (Simplified for String Translation Testing)
 */
class LingoLocalizationPlugin
{
    private $options;

    public function __construct()
    {
        // Load options on init
        add_action('init', array($this, 'init'));

        // Add admin menu page
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'admin_init'));

        // AJAX handler for string translation test
        add_action('wp_ajax_lingo_test_translate_string', array($this, 'ajax_test_translate_string'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Initialize plugin, load options.
     */
    public function init()
    {
        $this->options = get_option('lingo_localization_options', array());
        // Lingo engine is initialized on demand within the AJAX handler
        // to ensure it uses the most current saved API key.
    }

    /**
     * Add the plugin settings page to the WordPress admin menu.
     */
    public function add_admin_menu()
    {
        add_options_page(
            'Lingo Translation Test',      // Page title
            'Lingo Translation Test',      // Menu title
            'manage_options',              // Capability required to access
            'lingo-wp-translator-test',    // Menu slug
            array($this, 'admin_page')     // Callback function to display the page content
        );
    }

    /**
     * Register plugin settings and fields.
     */
    public function admin_init()
    {
        register_setting('lingo_localization_settings', 'lingo_localization_options');

        add_settings_section(
            'lingo_api_settings',
            'API Settings',
            array($this, 'api_settings_callback'),
            'lingo_localization_settings'
        );

        add_settings_field(
            'api_key',
            'API Key',
            array($this, 'api_key_callback'),
            'lingo_localization_settings',
            'lingo_api_settings'
        );

        add_settings_field(
            'default_source_locale',
            'Default Source Language',
            array($this, 'source_locale_callback'),
            'lingo_localization_settings',
            'lingo_api_settings'
        );

        add_settings_field(
            'target_locales',
            'Available Target Languages',
            array($this, 'target_locales_callback'),
            'lingo_localization_settings',
            'lingo_api_settings'
        );

        add_settings_field(
            'fast_mode',
            'Fast Mode',
            array($this, 'fast_mode_callback'),
            'lingo_localization_settings',
            'lingo_api_settings'
        );
    }

    /**
     * Display the plugin's admin settings page content.
     */
    public function admin_page()
    {
        ?>
        <div class="wrap">
            <h1>Lingo Translation Test Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('lingo_localization_settings');
                do_settings_sections('lingo_localization_settings');
                submit_button();
                ?>
            </form>

            <hr style="margin: 30px 0;">

            <div class="lingo-test-section">
                <h2>Test String Translation</h2>
                <p>Enter a string below and select a target language to test the translation engine.</p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="lingo-test-string">Text to Translate</label></th>
                        <td>
                            <textarea id="lingo-test-string" class="large-text" rows="5" placeholder="Enter text here..."></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="lingo-test-target-locale">Target Language</label></th>
                        <td>
                            <select id="lingo-test-target-locale">
                                <?php
                                // Populate target languages from saved options
                                $target_locales = explode(',', $this->options['target_locales'] ?? '');
                                $target_locales = array_filter(array_map('trim', $target_locales)); // Clean and filter empty

                                if (empty($target_locales)) {
                                    echo '<option value="">Please configure target languages in settings above.</option>';
                                } else {
                                    echo '<option value="">Select a language</option>';
                                    foreach ($target_locales as $locale) {
                                        echo '<option value="' . esc_attr($locale) . '">' . esc_html($locale) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="button" id="lingo-translate-string-button" class="button button-primary">Translate String</button>
                    <span class="spinner" id="lingo-test-spinner" style="display: none;"></span>
                </p>

                <div id="lingo-test-result-area" style="margin-top: 20px;">
                    <h3>Translation Result:</h3>
                    <div id="lingo-test-result">
                        <p>No translation performed yet.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for testing string translation.
     */
    public function ajax_test_translate_string()
    {
        // Verify AJAX nonce for security
        check_ajax_referer('lingo_ajax_nonce', 'nonce');

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions.');
        }

        // Sanitize and retrieve input data
        $input_string = sanitize_textarea_field($_POST['string_to_translate']);
        $target_locale = sanitize_text_field($_POST['target_locale']);

        // Validate inputs
        if (empty($input_string) || empty($target_locale)) {
            wp_send_json_error('Text to translate and target language are required.');
        }

        // Get current options to initialize the Lingo engine
        $options = get_option('lingo_localization_options', array());
        $api_key = $options['api_key'] ?? '';
        $source_locale = $options['default_source_locale'] ?? 'en';
        $fast_mode = (bool)($options['fast_mode'] ?? false);

        if (empty($api_key)) {
            wp_send_json_error('Lingo.dev API Key is not set in settings. Please configure it and save changes.');
        }

        try {
            // Initialize LingoDotDevEngine
            $lingo_engine = new LingoDotDevEngine([
                'apiKey' => $api_key,
                // Uncomment below to add batchSize and idealBatchItemSize here if you want them to apply to API calls
                // 'batchSize' => $options['batch_size'] ?? 25,
                // 'idealBatchItemSize' => $options['ideal_batch_size'] ?? 250
            ]);

            // Prepare the string for translation as an object
            // Lingo.dev's localizeObject expects an associative array.
            $content_to_translate = [
                'test_string' => $input_string, // Using a generic key for testing
            ];

            // Define translation parameters
            $params = [
                'sourceLocale' => $source_locale,
                'targetLocale' => $target_locale,
                'fast' => $fast_mode,
            ];

            // Perform the translation
            $translated_content = $lingo_engine->localizeObject($content_to_translate, $params);

            // Extract the translated string from the result
            $translated_string = $translated_content['test_string'] ?? 'Error: Translated string not found in Lingo.dev response.';

            // Send success response with original and translated text
            wp_send_json_success(array(
                'original'      => $input_string,
                'translated'    => $translated_string,
                'target_locale' => $target_locale
            ));

        } catch (Exception $e) {
            // Catch any other unexpected PHP exceptions
            wp_send_json_error('An unexpected error occurred: ' . $e->getMessage());
        }
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on our specific admin settings page
        if ($hook === 'settings_page_lingo-wp-translator-test') {
            wp_enqueue_script(
                'lingo-admin-test-js',
                LINGO_PLUGIN_URL . 'assets/admin-test.js',
                array('jquery'),
                LINGO_PLUGIN_VERSION,
                true // Load in footer
            );
            
            wp_enqueue_style(
                'lingo-admin-test-css',
                LINGO_PLUGIN_URL . 'assets/admin-test.css',
                array(),
                LINGO_PLUGIN_VERSION
            );
            
            // Pass PHP variables to our JavaScript
            wp_localize_script('lingo-admin-test-js', 'lingoAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('lingo_ajax_nonce') // Security nonce
            ));
        }
    }

    /* --- Settings Field Callbacks --- */

    public function api_settings_callback()
    {
        echo '<p>Configure your Lingo.dev API settings below.</p>';
    }
    
    public function api_key_callback()
    {
        $value = $this->options['api_key'] ?? '';
        echo '<input type="password" name="lingo_localization_options[api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Get your API key from <a href="https://lingo.dev" target="_blank">Lingo.dev</a></p>';
    }
    
    public function source_locale_callback()
    {
        $value = $this->options['default_source_locale'] ?? 'en';
        echo '<input type="text" name="lingo_localization_options[default_source_locale]" value="' . esc_attr($value) . '" class="small-text" />';
        echo '<p class="description">Default source language code (e.g., en, es, fr)</p>';
    }
    
    public function target_locales_callback()
    {
        $value = $this->options['target_locales'] ?? '';
        echo '<input type="text" name="lingo_localization_options[target_locales]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Comma-separated list of target language codes (e.g., es, fr, de, it). These will populate the dropdown for testing.</p>';
    }
    
    public function fast_mode_callback()
    {
        $value = $this->options['fast_mode'] ?? false;
        $checked = $value ? 'checked' : '';
        echo '<input type="checkbox" name="lingo_localization_options[fast_mode]" value="1" ' . $checked . ' />';
        echo '<p class="description">Enable fast translation mode (may be less accurate but faster)</p>';
    }
}

// Initialize the plugin
new LingoLocalizationPlugin();

// Activation hook
// register_activation_hook(__FILE__, function() {
    
// });

// Deactivation hook
// register_deactivation_hook(__FILE__, function() {
    
// });