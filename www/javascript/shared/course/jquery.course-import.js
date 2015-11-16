/**
 * Event listeners for the course importer.
 */
(function($) {
    $.fn.setupCourseImport = function() {
        var form = $(this);

        // Setup enable/disable of courses within the form, and do initial setup
        var enableUnitClickEvent = function(element) {
            var row            = element.closest('[data-type="unit_row"]');
            var inputs         = row.find(':input').not(element);
            var is_now_enabled = element.prop('checked');
            inputs.prop('disabled', !is_now_enabled);
            row.data('enabled', is_now_enabled);
        };
        form.on('change.courseImport', 'input[data-interaction="enable"]', function() {
            enableUnitClickEvent($(this));
        });
        form.find('input[data-interaction="enable"]')
            .each(function() {
                enableUnitClickEvent($(this));
            })
            // Add a delete prompt when unchecking a unit that is in the course
            .filter(':checked')
            .on('click.courseImport', function(e) {
                var element = $(this);
                if (!element.prop('checked') && element.data('unitInstanceId')) {
                    e.preventDefault();
                    element.confirmDialog({
                        text: 'Removing a unit from a class will delete the unit and all of its tasks. Are you sure you wish to do this? (They may be restored by adding the unit back to the class through this page.)',
                        confirmEvent: function() {
                            element.prop('checked', false).trigger('change.courseImport');
                        }
                    });
                }
            });

        // Form validation: Run the validation on submit only
        form.validate();

        // Open date validation
        form.find('[data-role="open_date"]').each(function() {
            $(this).rules('add', {
                datepickerDate: true,
                messages: {
                    datepickerDate: 'Please enter a valid date.'
                }
            });
        });
        form.find('[data-role="due_date"]').each(function() {
            $(this).rules('add', {
                datepickerDate: true,
                datepickerDateNotBefore: {
                    param: function(this_element) {
                        return $(this_element).closest('[data-type="unit_row"]').find('[data-role="open_date"]');
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
