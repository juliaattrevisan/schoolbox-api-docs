/**
 * Form that is submitted by AJAX.
 *
 * Includes ability to retry.
 *
 * This triggers the following events on the form:
 * * beforeSubmit.ajaxForm: Before the AJAX submission is run
 * * done.ajaxForm:         When AJAX submission runs successfully
 * * fail.ajaxForm:         When AJAX submission fails
 * * always.ajaxForm:       After either done.ajaxForm or fail.ajaxForm
 * * retry.ajaxForm:        After fail.ajaxForm, and user opts to retry
 * * abort.ajaxForm:        After fail.ajaxForm, and user opts to not retry
 */
(function($){
    "use strict";

    /**
     * Constructor
     *
     * @param form    The form to apply this ajax form to
     * @param options AJAX command options
     * @constructor
     */
    var AjaxForm = function(form, options) {
        this.form    = form;
        this.options = options;
    };

    /**
     * Handles when the form doesn't save.
     */
    AjaxForm.prototype.handleFailure = function() {
        var self = this;
        if (confirm('form did not save. retry?')) {
            self.form.trigger('retry.ajaxForm');
            self.submitForm();
        } else {
            // set it back the way it was
            self.form.trigger('reset');
            self.form.trigger('abort.ajaxForm');
        }
    };

    /**
     * Submits the form via AJAX, and handles ajax form events.
     *
     * @returns {jqXHR}
     */
    AjaxForm.prototype.submitForm = function() {
        // Build options out of form if they aren't there
        var self        = this;
        var form        = this.form;
        var ajaxOptions = this.options;
        if (!('url' in ajaxOptions)) {
            ajaxOptions.url = form.attr('action');
        }
        if (!('data' in ajaxOptions)) {
            ajaxOptions.data = form.serializeArray();
        }
        if (!('method' in ajaxOptions || 'type' in ajaxOptions)) {
            ajaxOptions.method = form.attr('method');
        }
        form.trigger('beforeSubmit.ajaxForm');
        return $.ajax(ajaxOptions)
            .then(
                function(data, status, xhr) {
                    form.trigger('done.ajaxForm', [data, status, xhr]);
                },
                function(xhr, status, error) {
                    // Consider auto-retry w/ jitter?
                    form.trigger('fail.ajaxForm', [xhr, status, error]);
                    self.handleFailure();
                }
            )
            .always(function(xhr, status) {
                form.trigger('always.ajaxForm', [xhr, status]);
            });
    };

    /**
     * Sets up the ajaxForm submission
     *
     * @param options
     * @returns {jQuery}
     */
    $.fn.ajaxForm = function(options) {
        var ajaxForm = new AjaxForm(this, options);
        ajaxForm.submitForm();
        return this;
    };
})(jQuery);
