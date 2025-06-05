jQuery(document).ready(function ($) {
  // Handle click event for the "Translate String" button
  $("#lingo-translate-string-button").on("click", function () {
    var button = $(this); // The clicked button
    var originalTextarea = $("#lingo-test-string"); // Input field for text
    var targetLocaleSelect = $("#lingo-test-target-locale"); // Dropdown for target language
    var resultDiv = $("#lingo-test-result"); // Div to display results
    var spinner = $("#lingo-test-spinner"); // Loading spinner element

    var stringToTranslate = originalTextarea.val();
    var targetLocale = targetLocaleSelect.val();

    // Basic client-side validation
    if (stringToTranslate.trim() === "") {
      alert("Please enter text to translate.");
      return;
    }
    if (targetLocale === "" || targetLocale === null) {
      alert("Please select a target language.");
      return;
    }

    // Show spinner, disable button, clear previous results
    spinner.css("display", "inline-block");
    button.prop("disabled", true);
    resultDiv.html("<p>Translating...</p>"); // Loading message

    // Perform AJAX call to WordPress
    $.ajax({
      url: lingoAjax.ajaxurl, // WordPress AJAX URL from wp_localize_script
      type: "POST",
      data: {
        action: "lingo_test_translate_string", // The wp_ajax_ hook defined in PHP
        nonce: lingoAjax.nonce, // Security nonce from wp_localize_script
        string_to_translate: stringToTranslate,
        target_locale: targetLocale,
      },
      success: function (response) {
        // Handle successful AJAX response
        if (response.success) {
          resultDiv.html(
            "<p><strong>Original (" +
              lingoAjax.sourceLocale +
              "):</strong> " +
              response.data.original +
              "</p>" +
              "<p><strong>Translated (" +
              response.data.target_locale +
              "):</strong> " +
              response.data.translated +
              "</p>"
          );
        } else {
          // Display error message from PHP
          resultDiv.html(
            '<p style="color: red;">Error: ' + response.data + "</p>"
          );
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        // Handle AJAX communication errors
        resultDiv.html(
          '<p style="color: red;">AJAX Communication Error: ' +
            textStatus +
            " - " +
            errorThrown +
            "</p>"
        );
      },
      complete: function () {
        // This runs regardless of success or error
        spinner.css("display", "none"); // Hide spinner
        button.prop("disabled", false); // Re-enable button
      },
    });
  });
});
