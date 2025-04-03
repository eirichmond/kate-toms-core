/******/ (() => { // webpackBootstrap
/*!**********************************************!*\
  !*** ./blocks/button-form-extension/view.js ***!
  \**********************************************/
jQuery(document).ready(function ($) {
  const buttons = $('div.wp-block-button[data-show-form="true"]');

  // Create overlay element once
  const overlay = $('<div>', {
    class: 'kt-form-overlay'
  }).appendTo('body');
  function closeForm(formContainer) {
    $('body').removeClass('has-form-open');
    formContainer.removeClass('is-open');
    overlay.removeClass('is-visible');

    // Remove the container and unbind events after animation completes
    setTimeout(function () {
      formContainer.remove();
      $(document).off('keyup.ktform');
    }, 300); // Match the CSS transition duration
  }
  buttons.each(function () {
    $(this).find('a.wp-block-button__link').on('click', function (e) {
      e.preventDefault();
      const formType = $(this).closest('[data-form-type]').data('formType');
      const formContainer = $('<div>', {
        class: 'kt-form-modal',
        html: `
                    <div class="kt-form-modal-content">
                        <button class="kt-form-modal-close">&times;</button>
                        <div class="kt-form-modal-body"></div>
                    </div>
                `
      });
      $('body').append(formContainer);

      // Add body class and trigger reflow for smooth animation
      setTimeout(function () {
        $('body').addClass('has-form-open');
        formContainer.addClass('is-open');
        overlay.addClass('is-visible');
      }, 50);
      const formBody = formContainer.find('.kt-form-modal-body');
      formBody.html('<p>Loading form...</p>');

      // Load form content based on type
      $.ajax({
        url: ktFormSettings.ajaxUrl,
        type: 'POST',
        data: {
          action: formType === 'booking' ? 'load_booking_form' : 'load_contact_form',
          nonce: ktFormSettings.nonce
        },
        success: function (response) {
          if (response.success) {
            formBody.html(response.data.html);
          } else {
            formBody.html('<p>Error loading form. Please try again.</p>');
          }
        },
        error: function () {
          formBody.html('<p>Error loading form. Please try again.</p>');
        }
      });

      // Close panel functionality
      formContainer.on('click', '.kt-form-modal-close', function () {
        closeForm(formContainer);
      });

      // Close on overlay click
      overlay.on('click', function () {
        closeForm(formContainer);
      });

      // Handle escape key
      $(document).on('keyup.ktform', function (e) {
        if (e.key === 'Escape') {
          closeForm(formContainer);
        }
      });
    });
  });
});
/******/ })()
;
//# sourceMappingURL=view.js.map