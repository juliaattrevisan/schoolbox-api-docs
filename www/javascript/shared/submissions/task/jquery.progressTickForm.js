/**
 * Plugin for the task progress tick form.
 */
(function($) {
    "use strict";

    // Form submit/return values for each config type
    var statusValues = {
        incomplete:      1,
        selfAssessed:    2,
        teacherAssessed: 3
    };

    var defaults = {
        // Toast label
        label: false,

        // Button title for each status
        titles: {
            incomplete:      'Incomplete',
            selfAssessed:    'Self-assessed',
            teacherAssessed: 'Complete'
        },

        // Transitions
        // key:   old status
        // value: new status
        transitions: {
            incomplete:      'teacherAssessed',
            selfAssessed:    'teacherAssessed',
            teacherAssessed: 'incomplete'
        },

        // Change callback
        onChange: function(newStatus) {}
    };

    $.fn.progressTickForm = function(options) {
        var config = $.extend(true, {}, defaults, options);

        // install progress tick form on each
        this.each(function() {
            var form = $(this);

            // form.find('a[data-progress-tick-status]')
            //     .prop('disabled', false)
            //     .removeAttr('disabled');

            form.on('click.progressTickForm', 'a[data-progress-tick-status]', function(event) {
                event.preventDefault();

                var element = $(this),
                    label = $(config.label);

                // Toast if we can
                if (label.length && 'toastActivate' in label) {
                    label.toastActivate();
                }

                // Look up status to transition to, based on current status
                var formData = [
                    {
                        name: 'status',
                        value: statusValues[config.transitions[element.data('progressTickStatus')]]
                    }
                ];

                // Disable form for aesthetic reasons
                form.attr('disabled', 'disabled');

                // And transition
                $.ajax({
                    url: form.attr('action'),
                    type: form.attr('method') || 'post',
                    dataType: 'json',
                    data: formData
                })
                .done(function(data) {
                    // Update something.
                    // I guess add the same class the update submission has.
                    element.removeClass('pending reviewed');
                    // Update name, class and value
                    var statusString = '';
                    switch (data.status) {
                        case statusValues.incomplete:
                            statusString = 'incomplete';
                            break;
                        case statusValues.selfAssessed:
                            statusString = 'selfAssessed';
                            element.addClass('pending');
                            break;
                        case statusValues.teacherAssessed:
                            statusString = 'teacherAssessed';
                            element.addClass('reviewed');
                            break;
                    }
                    if (statusString) {
                        element
                            .data('progressTickStatus', statusString)
                            .attr('title', config.titles[statusString]);
                    }
                    // Toast if we can
                    if (label.length && 'toastTimeout' in label) {
                        label.toastTimeout({
                            text: 'Saved',
                            css: 'success'
                        });
                    }
                    // Callback
                    config.onChange.apply(form, [statusString])
                })
                .fail(function() {
                    // Toast if we can
                    if (label.length && 'toastTimeout' in label) {
                        label.toastTimeout({
                            text: 'Could not save!',
                            css: 'alert'
                        });
                    }
                })
                .always(function() {
                    // Re-enable form
                    form.removeAttr('disabled');
                });
            });
        })
    };

})(jQuery);
