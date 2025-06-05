jQuery(document).ready(function ($) {
  // Only proceed if lingoPost object is available (means we're on a post edit screen)
  if (typeof lingoPost === "undefined") {
    return;
  }

  var postId = lingoPost.postId;
  var ajaxurl = lingoPost.ajaxurl;
  var nonce = lingoPost.nonce;

  var translateButton = $("#lingo-translate-post-button");
  var targetLocaleSelect = $("#lingo-post-target-locale");
  var spinner = $("#lingo-post-spinner");
  var resultDiv = $("#lingo-post-translation-result");
  var existingTranslationsList = $("#lingo-existing-translations-list");
  var currentPostLanguageDisplay = $("#lingo-current-post-language-display");

  translateButton.on("click", function () {
    var targetLocale = targetLocaleSelect.val();

    if (targetLocale === "" || targetLocale === null) {
      resultDiv.html(
        '<p style="color: red;">Please select a target language.</p>'
      );
      return;
    }

    // Disable button, show spinner, clear previous results
    translateButton.prop("disabled", true);
    spinner.css("display", "inline-block");
    resultDiv.html("<p>Translating post... This may take a moment.</p>");

    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "lingo_translate_post",
        nonce: nonce,
        post_id: postId,
        target_locale: targetLocale,
      },
      success: function (response) {
        if (response.success) {
          resultDiv.html(
            '<p style="color: green;">' + response.data.message + "</p>"
          );
          if (response.data.translated_post_edit_link) {
            resultDiv.append(
              '<p><a href="' +
                response.data.translated_post_edit_link +
                '" target="_blank" class="button button-small">' +
                "Edit " +
                response.data.target_locale.toUpperCase() +
                " Translation" +
                "</a></p>"
            );
          }

          // --- Update the existing translations list dynamically ---
          var existingTranslationListItem = existingTranslationsList.find(
            'li a[href*="post=' + response.data.translated_post_id + '"]'
          );
          var newListItemHtml = "<li>";
          if (response.data.translated_post_id == postId) {
            // If it's the current post being translated (unlikely for now, but robust)
            newListItemHtml +=
              "<strong>" +
              response.data.target_locale.toUpperCase() +
              " (Current)</strong>";
          } else {
            newListItemHtml +=
              '<a href="' +
              response.data.translated_post_edit_link +
              '" target="_blank">' +
              response.data.target_locale.toUpperCase() +
              " (ID: " +
              response.data.translated_post_id +
              ")</a>";
          }
          newListItemHtml += "</li>";

          if (response.data.action_type === "created") {
            // If no translations were found before, clear the "No translations found yet." message
            if (
              existingTranslationsList
                .text()
                .includes("No translations found yet.")
            ) {
              existingTranslationsList.empty();
            }
            existingTranslationsList.append(newListItemHtml);
          } else if (response.data.action_type === "updated") {
            // If an existing item for this translation already exists, update it
            if (existingTranslationListItem.length > 0) {
              existingTranslationListItem.parent().replaceWith(newListItemHtml);
            } else {
              // Fallback if not found, just append (shouldn't happen with correct linking)
              existingTranslationsList.append(newListItemHtml);
            }
          }

          // --- Update the lingoPost.translationGroup data for future clicks ---
          // This is key to making the dropdown update correctly without a full page reload.
          lingoPost.translationGroup[response.data.target_locale] =
            response.data.translated_post_id;

          // --- Update original post language display if it was empty ---
          if (currentPostLanguageDisplay.text().includes("Not set")) {
            currentPostLanguageDisplay.text(
              lingoPost.currentPostLocale.toUpperCase()
            );
          }

          // Re-populate the dropdown.
          populateTargetLocaleDropdown(
            targetLocaleSelect,
            lingoPost.targetLocales,
            lingoPost.currentPostLocale
          );

          // After success, reset the dropdown to its default "Select a language" state
          targetLocaleSelect.val("");
        } else {
          resultDiv.html(
            '<p style="color: red;">Translation Failed: ' +
              response.data +
              "</p>"
          );
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        resultDiv.html(
          '<p style="color: red;">AJAX Error: ' +
            textStatus +
            " - " +
            errorThrown +
            "</p>"
        );
      },
      complete: function () {
        spinner.css("display", "none");
        translateButton.prop("disabled", false);
      },
    });
  });

  // Function to re-populate the target locale dropdown
  function populateTargetLocaleDropdown(
    selectElement,
    availableLocales,
    currentPostLocale
  ) {
    selectElement.empty(); // Clear existing options
    selectElement.append('<option value="">Select a language</option>'); // Add default option

    if (availableLocales && availableLocales.length > 0) {
      $.each(availableLocales, function (index, locale) {
        // Only exclude the current post's own language from the dropdown
        if (locale !== currentPostLocale) {
          selectElement.append(
            '<option value="' + locale + '">' + locale + "</option>"
          );
        }
      });
    } else {
      selectElement.append(
        '<option value="">Configure target languages in Lingo Settings.</option>'
      );
    }
  }

  // Initial call to populate dropdown when the page loads
  populateTargetLocaleDropdown(
    targetLocaleSelect,
    lingoPost.targetLocales,
    lingoPost.currentPostLocale
  );
});
