<div class="lingo-post-translation-meta-box">
    <input type="hidden" id="lingo-current-post-id" value="<?php echo esc_attr($post->ID); ?>" />
    
    <p><strong>Current Post Language:</strong> 
        <span id="lingo-current-post-language-display">
            <?php echo !empty($current_post_language) ? esc_html($current_post_language) : 'Not set (will be set to Default Source Language upon first translation)'; ?>
        </span>
    </p>

    <div class="lingo-translation-status">
        <h4>Existing Translations:</h4>
        <ul id="lingo-existing-translations-list">
            <?php if (!empty($translation_group) && is_array($translation_group)) : ?>
                <?php foreach ($translation_group as $locale => $linked_post_id) : ?>
                    <li>
                        <?php if ($linked_post_id == $post->ID) : // This is the current post ?>
                            <strong><?php echo esc_html(strtoupper($locale)); ?> (Current)</strong>
                        <?php else : ?>
                            <a href="<?php echo esc_url(get_edit_post_link($linked_post_id)); ?>" target="_blank">
                                <?php echo esc_html(strtoupper($locale)); ?> (ID: <?php echo absint($linked_post_id); ?>)
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php else : ?>
                <li>No translations found yet.</li>
            <?php endif; ?>
        </ul>
    </div>

    <hr>

    <h4>Translate This Post:</h4>
    <p>Select a target language to translate this post's title, content, and excerpt.</p>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="lingo-post-target-locale">Target Language</label></th>
            <td>
                <select id="lingo-post-target-locale">
                    <?php if (empty($target_locales)) : ?>
                        <option value="">Configure target languages in Lingo Settings.</option>
                    <?php else : ?>
                        <option value="">Select a language</option>
                        <?php foreach ($target_locales as $locale) : ?>
                            <?php 
                            if ($locale === $current_post_language) continue; 
                            if (isset($translation_group[$locale])) continue;
                            ?>
                            <option value="<?php echo esc_attr($locale); ?>"><?php echo esc_html($locale); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </td>
        </tr>
    </table>

    <p class="submit">
        <button type="button" id="lingo-translate-post-button" class="button button-primary">Translate Post</button>
        <span class="spinner" id="lingo-post-spinner" style="display: none;"></span>
    </p>

    <div id="lingo-post-translation-result" style="margin-top: 15px;">
        </div>
</div>