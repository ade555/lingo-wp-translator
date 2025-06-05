<div class="wrap">
    <h1>Lingo Translation Settings</h1>
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