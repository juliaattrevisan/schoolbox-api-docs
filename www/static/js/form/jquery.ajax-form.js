/**
 * Form that is submitted by AJAX.
 *
 * Includes ability to retry.
 *
 * This triggers the following events on the form:
 * * beforeAttemptingSubmit.ajaxForm: Before the AJAX submission is run
 * * done.ajaxForm:         When AJAX submission runs successfully
 * * fail.ajaxForm:         When AJAX submission fails
 * * always.ajaxForm:       After either done.ajaxForm or fail.ajaxForm
 * * retry.ajaxForm:        After fail.ajaxForm, and user opts to retry
 * * abort.ajaxForm:        After fail.ajaxForm, and user opts to not retry
 *
 * TODO: investigate whether this needs to be renamed.
 *       jQuery validation defines $.fn.ajaxForm, but Schoolbox doesn't use it.
 */
(function ($) {
    "use strict";

    /**
     * Constructor
     *
     * @param form    The form to apply this ajax form to
     * @param options AJAX command options
     * @constructor
     */
    var AjaxForm = function (form, options) {
        this.form = form;
        this.options = options;
    };

    /**
     * Handles when the form doesn't save.
     */
    AjaxForm.prototype.handleFailure = function () {
        this.buildRevealModal().foundation("reveal", "open");
    };

    /**
     * Attempts to submit the form again
     */
    AjaxForm.prototype.retrySubmit = function () {
        this.form.trigger("retry.ajaxForm");
        this.submitForm();
    };

    /**
     * Aborts submission, and resets form
     */
    AjaxForm.prototype.abortSubmit = function () {
        this.form.trigger("reset.ajaxForm").trigger("abort.ajaxForm");
    };

    /**
     * Creates a reveal modal for asking whether to retry or cancel
     *
     * @returns {jQuery} The reveal modal
     */
    AjaxForm.prototype.buildRevealModal = function () {
        var self = this;
        var modal = $(
            '<div class="reveal-modal" id="retry-modal" data-reveal aria-hidden="true" role="dialog">' +
                "<h2>Save failed</h2>" +
                "<p>Do you wish to retry?</p>" +
                '<ul class="flex-list buttons">' +
                '<li><a href="#" class="button submit" data-action="retry">Retry</a></li>' +
                '<li><a href="#" class="button" data-action="abort">Cancel</a></li>' +
                "</ul>" +
                "</div>",
        );

        modal.appendTo("body");

        return modal
            .on("click", '[data-action="retry"]', function (e) {
                modal.foundation("reveal", "close");
                modal.remove();
                e.preventDefault();
                self.retrySubmit();
            })
            .on("click", '[data-action="abort"]', function (e) {
                modal.foundation("reveal", "close");
                modal.remove();
                e.preventDefault();
                self.abortSubmit();
            });
    };

    /**
     * Submits the form via AJAX, and handles ajax form events.
     *
     * @returns {jqXHR}
     */
    AjaxForm.prototype.submitForm = function () {
        // Build options out of form if they aren't there
        var self = this;
        var form = this.form;
        var ajaxOptions = this.options;
        if (!("url" in ajaxOptions)) {
            ajaxOptions.url = form.attr("action");
        }
        if (!("data" in ajaxOptions)) {
            ajaxOptions.data = form.serializeArray();
        }
        if (!("method" in ajaxOptions || "type" in ajaxOptions)) {
            ajaxOptions.method = form.attr("method");
        }
        form.trigger("beforeAttemptingSubmit.ajaxForm");
        return $.ajax(ajaxOptions)
            .then(
                function (data, status, xhr) {
                    form.trigger("done.ajaxForm", [data, status, xhr]);
                },
                function (xhr, status, error) {
                    // Consider auto-retry w/ jitter?
                    form.trigger("fail.ajaxForm", [xhr, status, error]);
                    self.handleFailure();
                },
            )
            .always(function (xhr, status) {
                form.trigger("always.ajaxForm", [xhr, status]);
            });
    };

    /**
     * Sets up the ajaxForm submission
     *
     * @param options
     * @returns {jQuery}
     */
    $.fn.ajaxForm = function (options) {
        var ajaxForm = new AjaxForm(this, options);
        ajaxForm.submitForm();
        return this;
    };
})(jQuery);
