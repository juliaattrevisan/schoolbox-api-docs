/**
 * Event listeners for the course importer.
 */
(function($) {
    $.fn.setupCourseImport = function() {
        var form = $(this);

        // Setup datepickers
        form.find('input[data-interaction="datepicker"]').datepicker({
            dateFormat:'dd/mm/yy',
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

        // Setup enable/disable of courses within the form
        form.delegate('input[data-interaction="enable"]', 'click', function(event) {
            var element = $(this);
            var row     = element.closest('[data-type="unit_row"]');
            if (!element.is(':checked')) {
                row.find(':input').not(this).prop('disabled', true);
                row.data('enabled', false);
            }
            else {
                row.find(':input').not(this).prop('disabled', false);
                row.data('enabled', true);
            }
        });

        // Form validation: Run the validation on any blur event on a field
        form.validate();
        form.delegate('input', 'blur', function() { form.valid(); })

        // Open date validation
//        var errorPlacerCallback = function(error, element) {
//            error.insertAfter(element.parent('td').find('.ui-datepicker-trigger'));
//        }
        form.find('input.open_date').each(function() {
            $(this).rules('add', {
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
