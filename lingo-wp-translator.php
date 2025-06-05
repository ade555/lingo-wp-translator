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

        // Custom taxonomy for languages
        add_action('init', array($this, 'register_lingo_language_taxonomy'));

        // Add admin menu page
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'admin_init'));

         // Add meta box to post edit screen
        add_action('add_meta_boxes', array($this, 'add_post_translation_meta_box'));

        // AJAX handler for string translation test. This is used in the admin settings
        add_action('wp_ajax_lingo_test_translate_string', array($this, 'ajax_test_translate_string'));

         // AJAX handler for post translation
        add_action('wp_ajax_lingo_translate_post', array($this, 'ajax_translate_post'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Initialize plugin, load options.
     */
    public function init()
    {
        $this->options = get_option('lingo_localization_options', array());
        // Lingo engine is initialized on demand within the AJAX handler to ensure it uses the most current saved API key.
    }

    /**
     * Register a custom taxonomy for Lingo translation languages.
     * This will be used to assign a language to each post and to link translations.
     */
     public function register_lingo_language_taxonomy() {
        $labels = array(
            'name'              => _x( 'Languages', 'taxonomy general name', 'lingo-wp-translator' ),
            'singular_name'     => _x( 'Language', 'taxonomy singular name', 'lingo-wp-translator' ),
            'search_items'      => __( 'Search Languages', 'lingo-wp-translator' ),
            'all_items'         => __( 'All Languages', 'lingo-wp-translator' ),
            'parent_item'       => __( 'Parent Language', 'lingo-wp-translator' ),
            'parent_item_colon' => __( 'Parent Language:', 'lingo-wp-translator' ),
            'edit_item'         => __( 'Edit Language', 'lingo-wp-translator' ),
            'update_item'       => __( 'Update Language', 'lingo-wp-translator' ),
            'add_new_item'      => __( 'Add New Language', 'lingo-wp-translator' ),
            'new_item_name'     => __( 'New Language Name', 'lingo-wp-translator' ),
            'menu_name'         => __( 'Languages', 'lingo-wp-translator' ),
        );
        $args = array(
            'hierarchical'      => false, // Languages are usually not hierarchical
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'language' ),
        );
        register_taxonomy('lingo_language', array('post', 'page'), $args); // Apply to posts and pages
    }


    /**
     * Add the plugin settings page to the WordPress admin menu.
     */
    public function add_admin_menu()
    {
        add_options_page(
            'Lingo Translation Settings',      // Page title
            'Lingo Translation Settings',      // Menu title
            'manage_options',              // Capability required to access
            'lingo-wp-translator-settings',    // Menu slug
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
        include LINGO_PLUGIN_PATH . 'admin/settings-page.php';
    }

    /**
     * Add a meta box to the post and page edit screens.
     */
    public function add_post_translation_meta_box() {
        add_meta_box(
            'lingo_translation_meta_box',
            __( 'Lingo Translation', 'lingo-wp-translator' ),
            array( $this, 'display_post_translation_meta_box' ),
            array( 'post', 'page' ),
            'side',
            'high'
        );
    }

    /**
     * Display the content of the post translation meta box.
     */
    public function display_post_translation_meta_box($post) {
        // Get target locales from plugin options
        $target_locales_str = $this->options['target_locales'] ?? '';
        $target_locales = array_filter(array_map('trim', explode(',', $target_locales_str)));

        // Get the current post's language (if set)
        $current_post_languages = wp_get_post_terms($post->ID, 'lingo_language', array('fields' => 'names'));
        $current_post_language = !empty($current_post_languages) ? $current_post_languages[0] : '';

        // Get existing translations for this post
        // We'll store a map like ['en' => 123, 'es' => 456] in post meta
        $translation_group = get_post_meta($post->ID, '_lingo_translation_group', true);
        if (!is_array($translation_group)) {
            $translation_group = [];
        }
        
        include LINGO_PLUGIN_PATH . 'admin/post-meta-box.php';
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
     * AJAX handler for post translation.
     * Creates a new translated post and links it to the original.
     */
    public function ajax_translate_post() {
        // Verify AJAX nonce and user capabilities
        check_ajax_referer('lingo_post_translation_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { // Use 'edit_posts' capability for post actions
            wp_send_json_error('Insufficient permissions.');
        }

        $post_id       = absint($_POST['post_id']);
        $target_locale = sanitize_text_field($_POST['target_locale']);

        if (!$post_id || empty($target_locale)) {
            wp_send_json_error('Invalid post ID or target language.');
        }

        $original_post = get_post($post_id);
        if (!$original_post) {
            wp_send_json_error('Original post not found.');
        }

        // Ensure post content exists
        $post_content = $original_post->post_content;
        $post_title = $original_post->post_title;
        $post_excerpt = $original_post->post_excerpt; // Include excerpt for translation

        if (empty($post_content) && empty($post_title)) {
            wp_send_json_error('Post has no content or title to translate.');
        }

        // Get Lingo API settings
        $options = get_option('lingo_localization_options', array());
        $api_key = $options['api_key'] ?? '';
        $source_locale = $options['default_source_locale'] ?? 'en';
        $fast_mode = (bool)($options['fast_mode'] ?? false);

        if (empty($api_key)) {
            wp_send_json_error('Lingo.dev API Key is not set in plugin settings. Please configure it.');
        }

        // Check for existing translation or initialize group 
        $translation_group = get_post_meta($post_id, '_lingo_translation_group', true);
        if (!is_array($translation_group)) {
            $translation_group = [];
        }

        $action_type = 'created'; // Default action type
        $existing_translated_post_id = 0;

        // Check if this post is already translated into the target locale
        if (isset($translation_group[$target_locale])) {
            $existing_translated_post_id = $translation_group[$target_locale];

            // Ensure the existing post actually exists and is not trashed/deleted
            if (get_post_status($existing_translated_post_id) !== false) {
                 $action_type = 'updated'; // We will update an existing post
            } else {
                // If it's in the map but doesn't exist, treat as new creation
                // and remove from map to clean up (optional, but good practice)
                unset($translation_group[$target_locale]);
                $action_type = 'created';

                // Also update the meta on the current post to reflect the removal if it exists
                update_post_meta($post_id, '_lingo_translation_group', $translation_group);
            }
        }

        try {
            $lingo_engine = new LingoDotDevEngine(['apiKey' => $api_key]);

            // Prepare content for translation as an object
            // This allows translating multiple fields (title, content, excerpt) in one API call
            $content_to_translate = [
                'post_title'   => $post_title,
                'post_content' => $post_content,
                'post_excerpt' => $post_excerpt,
            ];

            $params = [
                'sourceLocale' => $source_locale,
                'targetLocale' => $target_locale,
                'fast'         => $fast_mode,
            ];

            $translated_content = $lingo_engine->localizeObject($content_to_translate, $params);

            // Extract translated fields
            $translated_title   = $translated_content['post_title'] ?? $post_title; // Fallback to original if not translated
            $translated_content = $translated_content['post_content'] ?? $post_content;
            $translated_excerpt = $translated_content['post_excerpt'] ?? $post_excerpt;

            $new_post_id = 0; // Initialize outside the if/else for scop


              if ($action_type === 'created') {
                // --- Create the new translated post ---
                $translated_post_args = array(
                    'post_title'    => $translated_title,
                    'post_content'  => $translated_content,
                    'post_excerpt'  => $translated_excerpt,
                    'post_status'   => 'draft', // Create as draft initially
                    'post_type'     => $original_post->post_type,
                    'post_author'   => $original_post->post_author,
                    'comment_status' => $original_post->comment_status,
                    'ping_status'    => $original_post->ping_status,
                );

                $new_post_id = wp_insert_post($translated_post_args);

                if (is_wp_error($new_post_id)) {
                    wp_send_json_error('Failed to create new translated post: ' . $new_post_id->get_error_message());
                }

                // --- Link the posts (updates all related posts' meta) ---
                $this->link_translated_posts($post_id, $new_post_id, $target_locale, $source_locale);
                
                // Set the lingo_language taxonomy for the new post
                wp_set_post_terms($new_post_id, $target_locale, 'lingo_language');
                
                // Set the lingo_language taxonomy for the original post (if not already set)
                $current_post_languages = wp_get_post_terms($post_id, 'lingo_language', array('fields' => 'names'));
                if (empty($current_post_languages)) { // Check if original post has no language term
                    wp_set_post_terms($post_id, $source_locale, 'lingo_language');
                }

                $message = sprintf(__('Post translated to %s successfully! It is saved as a draft.', 'lingo-wp-translator'), esc_html($target_locale));

            } else { // $action_type === 'updated'
                $new_post_id = $existing_translated_post_id;
                // --- Update the existing translated post ---
                $updated_post_args = array(
                    'ID'           => $new_post_id,
                    'post_title'   => $translated_title,
                    'post_content' => $translated_content,
                    'post_excerpt' => $translated_excerpt,
                    // You might want to update post_status to 'draft' again if it was 'publish'
                    // or respect its current status. Let's keep it simple for now and update content only.
                );

                $result = wp_update_post($updated_post_args, true); // true to return WP_Error on failure

                if (is_wp_error($result)) {
                    wp_send_json_error('Failed to update existing translated post: ' . $result->get_error_message());
                }
                
                $message = sprintf(__('Existing %s translation updated successfully!', 'lingo-wp-translator'), esc_html($target_locale));
            }


            wp_send_json_success(array(
                'message'        => $message,
                'translated_post_id' => $new_post_id,
                'translated_post_edit_link' => get_edit_post_link($new_post_id),
                'target_locale'  => $target_locale,
                'action_type'    => $action_type, // Send action type back to JS
            ));


        } catch (Exception $e) {
            wp_send_json_error('Translation error: ' . $e->getMessage());
        }
    }

    /**
     * Helper function to link original and translated posts using a shared meta key.
     * This ensures all posts in a translation group know about each other.
     * 
     * @param int $original_post_id: The ID of the original post.
     * @param int $translated_post_id: The ID of the newly created translated post.
     * @param string $target_local: The locale of the translated post.
     * @param string $source_locale: The locale of the original post.
     */
    private function link_translated_posts($original_post_id, $translated_post_id, $target_locale, $source_locale) {
        // Get the existing translation group for the original post
        $translation_group = get_post_meta($original_post_id, '_lingo_translation_group', true);
        if (!is_array($translation_group)) {
            $translation_group = [];
        }

        // Add the original post itself to the group (if not already there)
        if (!isset($translation_group[$source_locale])) {
            $translation_group[$source_locale] = $original_post_id;
        }

        // Add the new translated post to the group
        $translation_group[$target_locale] = $translated_post_id;

        // Update all posts in the group with the complete translation map
        foreach ($translation_group as $locale => $post_id) {
            update_post_meta($post_id, '_lingo_translation_group', $translation_group);
        }
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts($hook)
    {
        // Only load on our specific admin settings page
        if ($hook === 'settings_page_lingo-wp-translator-settings') {
            wp_enqueue_script(
                'lingo-admin-test-js',
                LINGO_PLUGIN_URL . 'assets/js/admin-test.js',
                array('jquery'),
                LINGO_PLUGIN_VERSION,
                true // Load in footer
            );
            
            wp_enqueue_style(
                'lingo-admin-test-css',
                LINGO_PLUGIN_URL . 'assets/css/admin-test.css',
                array(),
                LINGO_PLUGIN_VERSION
            );
            
            // Pass PHP variables to JavaScript
            wp_localize_script('lingo-admin-test-js', 'lingoAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('lingo_ajax_nonce') // Security nonce
            ));
        }

         // For post/page edit screens
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_script(
                'lingo-admin-post-js',
                LINGO_PLUGIN_URL . 'assets/js/admin-post.js',
                array('jquery'),
                LINGO_PLUGIN_VERSION,
                true
            );
            wp_enqueue_style(
                'lingo-admin-post-css',
                LINGO_PLUGIN_URL . 'assets/css/admin-post.css',
                array(),
                LINGO_PLUGIN_VERSION
            );

            // Localize data for the post edit screen JavaScript
            global $post; // Get the current post object
            $target_locales_str = $this->options['target_locales'] ?? '';
            $target_locales = array_filter(array_map('trim', explode(',', $target_locales_str)));

            $current_post_languages = wp_get_post_terms($post->ID, 'lingo_language', array('fields' => 'names'));
            $current_post_language = !empty($current_post_languages) ? $current_post_languages[0] : '';
            
            $translation_group = get_post_meta($post->ID, '_lingo_translation_group', true);
            if (!is_array($translation_group)) {
                $translation_group = [];
            }

            wp_localize_script('lingo-admin-post-js', 'lingoPost', array(
                'ajaxurl'           => admin_url('admin-ajax.php'),
                'nonce'             => wp_create_nonce('lingo_post_translation_nonce'),
                'postId'            => $post->ID,
                'targetLocales'     => $target_locales,
                'currentPostLocale' => $current_post_language,
                'translationGroup'  => $translation_group,
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
register_activation_hook(__FILE__, function() {
    // Ensure the custom taxonomy is registered upon activation
    $plugin = new LingoLocalizationPlugin();
    $plugin->register_lingo_language_taxonomy();
    flush_rewrite_rules(); // Flush rewrite rules so the new taxonomy is recognized immediately
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules(); // Flush rewrite rules upon deactivation
});