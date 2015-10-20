/**
 * Form that is submitted by AJAX.
 *
 * Includes ability to retry
 */
(function($){
    "use strict";

    var getAjaxRequest = function(form, options) {
        // Build options out of form if they aren't there
        var ajaxOptions = options;
        if (!('url' in ajaxOptions)) {
            ajaxOptions.url = form.attr('action');
        }
        if (!('data' in ajaxOptions)) {
            ajaxOptions.data = form.serializeArray();
        }
        if (!('method' in ajaxOptions || 'type' in ajaxOptions)) {
            ajaxOptions.method = form.attr('method');
        }
        return $.ajax(ajaxOptions)
            .then(
                function(data, status, xhr) {
                    form.trigger('done.ajaxForm', [data, status, xhr]);
                },
                function(xhr, status, error) {
                    // Consider auto-retry w/ jitter?
                    form.trigger('fail.ajaxForm', [xhr, status, error]);
                }
            )
            .always(function(xhr, status) {
                form.trigger('always.ajaxForm', [xhr, status]);
            });
    };

    $.fn.ajaxForm = function(options) {
        var form = $(this);
        form.trigger('beforeSubmit.ajaxForm');
        getAjaxRequest(form, options);

        form.on('fail.ajaxForm', function() {
            // Raise error dialog
            // Obviously it won't stay a confirm dialog
            if (confirm('form did not save. retry?')) {
                getAjaxRequest(form, options);
                //form.trigger('failRetry.ajaxForm');
            } else {
                // set it back the way it was
                form.trigger('reset');
                //form.trigger('failRetry.ajaxForm');
            }
        });
        return form;
    };
})(jQuery);
