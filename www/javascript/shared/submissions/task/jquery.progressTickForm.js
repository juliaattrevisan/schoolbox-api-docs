/**
 * Plugin for the task progress tick form.
 */
(function($) {
    "use strict";

    var defaults = {
        label: false,
        button: 'a[data-interaction="update_progress"]',
        titles: {
            notAttempted: 'Not attempted',
            selfAssessed: 'Self-assessed',
            complete:     'Complete'
        },
        transitions: {
            notAttempted: 'complete',
            selfAssessed: 'notAttempted',
            complete:     'notAttempted'
        }
    };

    $.fn.progressTickForm = function(options) {
        var config = $.extend(true, {}, defaults, options);

        // install progress tick form on each
        this.each(function() {
            var form = $(this);

            form.on('click.progressTickForm', config.button, function(event) {
                event.preventDefault();

                var label = $(config.label);
                if ('toastActivate' in label) {
                    label.toastActivate();
                }

                var element = $(this);
                form.prop('disabled', true).attr('disabled', '');

                $.ajax({
                    url: form.attr('action'),
                    type: form.attr('method') || 'post',
                    dataType: 'json',
                    data: form.serialize()
                })
                    .done(function(data) {
                        // Update something.
                        // I guess add the same class the update submission has.
                        element.removeClass('pending complete').addClass('ajax-updated');
                        form.find('input[name="current_status"]').val(data.status);
                        // Update name and class as well
                        switch (data.status) {
                            case 1: // not attempted
                                element.attr('title', config.titles.notAttempted);
                                break;
                            case 2: // self-assessed
                                element.addClass('pending').attr('title', config.titles.selfAssessed);
                                break;
                            case 3: // complete
                                element.addClass('complete').attr('title', config.titles.complete);
                                break;
                        }

                        label.toastTimeout({
                            text: 'Saved',
                            css: 'success'
                        });

                    })
                    .fail(function() {
                        label.toastTimeout({
                            text: 'Could not save!',
                            css: 'alert'
                        });
                    })
                    .always(function() {
                        form.removeAttr('disabled');
                        setTimeout(function() {
                            element.removeClass('ajax-updated');
                        }, 1000);
                    });
                form.attr('disabled', 'disabled');
            });
        })
    };

})(jQuery);
