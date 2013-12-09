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
            confirmEvent: function() {
                var href = element.attr('href');
                if (href) {
                    location.href = element.attr('href');
                }
            },
            cancelLabel: 'No',
            destroyDialog: true
        };
        var settings = $.extend(true, {}, defaults, options);
        settings.dialogOptions.buttons = [
            {
                text:  settings.confirmLabel,
                click: function() {
                    settings.confirmEvent();
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
