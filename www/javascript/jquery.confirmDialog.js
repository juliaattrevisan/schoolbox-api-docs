/**
 * Quick and dirty method for creating a confirm dialog. By default it's
 * designed to work on a link.
 *
 * Options:
 * * dialog:        A selector for the dialog to open, defaults to creating a new one
 * * text:          Text to show in dialog
 * * dialogOptions: passthru to $.fn.dialog
 * * confirmLabel:  Label for "Yes" button
 * * cancelLabel:   Label for "No" button
 * * confirmEvent:  Callback that is run when Yes is clicked. Defaults to
 *                  following whatever link is on this element.
 * * destroyDialog: True if dialog is to be destroyed when it's closed.
 */
(function($){
    $.confirmDialog = {};

    /**
     * Common action: follow the href that is on the link that was clicked
     *
     * @param el
     */
    $.confirmDialog.followHref = function(el) {
        var href = el.attr('href');
        if (href) {
            location.href = el.attr('href');
        }
    };

    /**
     * Common action: submit the form that the button I clicked is in
     *
     * @param el
     */
    $.confirmDialog.submitForm = function(el) {
        var form = el.closest('form');
        if (form.length) {
            // pretend I clicked a submit button with the same name/value as
            // the button this was called on.
            // I'd just call form.submit() but get this:
            // it doesn't work if the form has an input with name=submit!
            // (because the element named "submit" overrides the DOM element's
            //  submit method)
            var input = $('<input type="submit" />')
                .attr('name', el.attr('name'))
                .val(el.val());
            form.append(input);
            input.click().remove();
        }
    };

    $.fn.confirmDialog = function(options) {
        var element  = $(this);
        var defaults = {
            dialog: $('<div />'),
            text: 'Are you sure?',
            dialogOptions: {
                modal: true,
                title: 'Confirm',
                close: function() {
                    if (settings.destroyDialog) {
                        $(this).dialog('destroy');
                    }
                }
            },
            confirmLabel: 'Yes',
            confirmEvent: $.confirmDialog.followHref,
            cancelLabel: 'No',
            destroyDialog: true
        };
        var settings = $.extend(true, {}, defaults, options);
        settings.dialogOptions.buttons = [
            {
                text:  settings.confirmLabel,
                click: function() {
                    settings.confirmEvent(element);
                    $(this).dialog('close');
                }
            },
            {
                text:  settings.cancelLabel,
                click: function() {
                    $(this).dialog('close');
                }
            }
        ];
        $(settings.dialog).text(settings.text).dialog(settings.dialogOptions);
    }
})(jQuery);
