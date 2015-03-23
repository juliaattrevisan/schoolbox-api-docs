/**
 * Event listeners for the course importer.
 */
(function($) {
    $.fn.setupCourseImport = function() {
        var form = $(this);

        // Setup datepickers
        form.find('input[data-interaction="datepicker"]').datepicker({
            showOn:'both',
            buttonImage:'/images/core/calendar/calendar-click.png',
            buttonImageOnly:true,
            constrainInput:false,
            onClose:function (string) {
                var input = $(this);
                $.getJSON('/ajax/strtotime.php', {string:string}, function (data) {
                    input.val(data.time);
                })
            }
        });

        // Setup enable/disable of courses within the form, and do initial setup
        var enableUnitClickEvent = function(element) {
            var row            = element.closest('[data-type="unit_row"]');
            var inputs         = row.find(':input:visible').not(element);
            var is_now_enabled = element.prop('checked');
            inputs.prop('disabled', !is_now_enabled);
            row.data('enabled', is_now_enabled);
        }
        form.on('click.courseImport', 'input[data-interaction="enable"]', function() {
            enableUnitClickEvent($(this));
        });
        form.find('input[data-interaction="enable"]').each(function() {
            enableUnitClickEvent($(this));
        })

        // Form validation: Run the validation on submit only
        form.validate({
            errorPlacement: function(error, element) {
                error.insertAfter(element.parent('td').find('.ui-datepicker-trigger'));
            }
        });

        // Open date validation and sync
        form.find('input.open_date').each(function() {
            var element = $(this)
            if ('syncDates' in $.fn) {
                element.syncDates({endDate: element.closest('[data-type="unit_row"]').find('input.due_date')})
            }
            element.rules('add', {
                datepickerDate: true,
                messages: {
                    datepickerDate: 'Please enter a valid date.'
                }
            });
        });
        form.find('input.due_date').each(function() {
            $(this).rules('add', {
                datepickerDate: true,
                datepickerDateNotBefore: {
                    param: function(this_element) {
                        return $(this_element).closest('[data-type="unit_row"]').find('input.open_date');
                    }
                },
                messages: {
                    datepickerDate: 'Please enter a valid date.',
                    datepickerDateNotBefore: 'Please enter a valid date not before the open date.'
                }
            });
        });
    }
})(jQuery);
