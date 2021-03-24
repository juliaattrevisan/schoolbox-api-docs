/**
 * Quick and dirty method for creating a confirm dialog. By default it's
 * designed to work on a link.
 *
 * Options:
 * * header:        Optional override for dialogue header text
 * * dialog:        A selector for the dialog to open, defaults to creating a new one
 * * text:          Text to show in dialog
 * * format:        Whether text is to be rendered as 'text' or 'html'
 * * dialogOptions: passthru to $.fn.dialog
 * * confirmLabel:  Label for "Yes" button
 * * cancelLabel:   Label for "No" button
 * * confirmEvent:  Callback that is run when 'Yes' is clicked. Defaults to
 *                  following whatever link is on this element.
 * * confirmEvent:  Callback that is run when 'No' is clicked. Defaults to no op.
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
            header: 'Please Confirm',
            text: 'Are you sure?',
            confirmLabel: 'Yes',
            confirmEvent: $.confirmDialog.followHref,
            cancelEvent: $.noop,
            cancelLabel: 'Cancel',
            format: 'text'
        };

        // Allow passing of defaults in data-vars
        // Override defaults, but not passed-in options
        var dataOptions = {};
        Object.keys(defaults).forEach(function (key) {
            var dataOption = element.data('confirmDialog-'+key);
            if (dataOption) {
                dataOptions[key] = dataOption;
            }
        });

        var settings = $.extend(true, {}, defaults, dataOptions, options);

        var modal        = $('<div data-reveal class="reveal-modal small" />');
        var h2 = $('<h2 />');
        h2.text(settings.header );

        var okayButton   = $('<a href="#" class="button submit" id="confirm-dialog-confirm-button"/>').text(settings.confirmLabel),
            cancelButton = $('<a href="#" class="button" id="confirm-dialog-cancel-button"/>').text(settings.cancelLabel),
            buttons      = $().add(okayButton).add(cancelButton);
        okayButton.on('click.confirmDialog', function(e) {
            // Immediately prevent further click events
            buttons.off('click.confirmDialog');
            e.preventDefault();
            settings.confirmEvent(element);
            // Don't trigger cancelEvent if I pressed OK
            modal.off('close.fndtn.reveal.cancel');
            modal.foundation('reveal', 'close');
        });
        cancelButton.on('click.confirmDialog', function(e) {
            // Immediately prevent further click events
            buttons.off('click.confirmDialog');
            e.preventDefault();
            // Cancel button doesn't trigger cancelEvent directly;
            // closing the modal does.
            // This is so clicking the X, or pressing ESC, triggers the cancel.
            modal.foundation('reveal', 'close');
        });

        var ul = $('<ul class="flex-list buttons" />');
        var li = $('<li />');
        ul.append(li.clone().append(okayButton));
        ul.append(li.clone().append(cancelButton));

        var pBody = $('<p />');

        if (settings.format == 'html') {
            pBody.html(settings.text);
        } else {
            pBody.text(settings.text);
        }

        modal
            .append(h2)
            .append(pBody)
            .append(ul)
            .append('<a class="close-reveal-modal" aria-label="Close">&#215;</a>');
        modal.appendTo($('body'));
        modal
            .foundation('reveal', 'reflow')
            .foundation('reveal', 'open')
            .on('close.fndtn.reveal.cancel', function() {
                settings.cancelEvent(element);
            });
    }
})(jQuery);
