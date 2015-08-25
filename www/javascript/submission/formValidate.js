/**
 * jQuery validation for the create form
 */
(function($) {
    "use strict";
    $.fn.submissionFormValidate = function() {
        // Load the form and that
        var form           = $(this);
        var acceptSubmit   = form.find('input:checkbox[name="accept_submission"]');
        var rubricCriteria = form.find('[data-role="rubric-criterion-selector"]');

        // Options for whether form options are validatable.
        var isAcceptSubmit = function() {
            return acceptSubmit.is(':checked');
        };
        var isMarkTypeRubric = function() {
            return form.find('[name="marktype"] :selected').data('slug') == 'rubric';
        };
        var isMarkTypeTotal = function() {
            return form.find('[name="marktype"] :selected').data('slug') == 'raw';
        };

        // Rubric validator: do any exist?
        $.validator.addMethod('markTypeRubricCriteriaExists', function() {
            return rubricCriteria.find('input[type="checkbox"]').length >= 1;
        }, 'Please ensure at least one criterion exists');

        // Rubric criteria: is at least one selected?
        $.validator.addMethod('markTypeRubricCriteriaCheck', function() {
            return rubricCriteria.find('input:checked').length >= 1;
        }, 'Please select at least one criterion');

        // Are rubric form values checked?
        var isRubricValueChecked = function(element) {
            if (!isMarkTypeRubric()) {
                return false;
            }
            // Only check if using assess specific descriptors
            if (!form.find('[data-relation="rubric-use-assessment-specific"]').prop('checked')) {
                return false;
            }
            // Is this rubric selected? Validate if so
            return $(element).closest('[data-role="criterion"]')
                    .find('[data-role="criterion-selector"]')
                    .prop('checked') === true;
        };

        // Validator for rubric values - they cannot be the same in the same criterion
        $.validator.addMethod(
            'markTypeRubricValuesCheck',
            function (value, element) {
                // All value inputs that aren't the currently validated one
                var criterion       = $(element).closest('[data-role="criterion"]');
                var duplicateValues = criterion.find('[data-role="criterion-value"]').not(element).filter(
                    function() {
                        // Filter out inputs that aren't in the same criterion,
                        // and don't have the same value
                        return this.name === element.name && this.value === element.value;
                    }
                );
                return duplicateValues.length === 0;
            },
            'Rubric values cannot be the same'
        );

        // Depend options
        var publish_date_valid = { dateValid: true };
        var open_date_valid = {
            dateValid: true,
            dateNotBefore: {
                message: 'Please enter a date that is not before the publish date.',
                param:   'publish',
                depends: isAcceptSubmit
            }
        };
        var due_date_valid  = {
            dateValid: true,
            dateNotBefore: {
                message: 'Please enter a date that is not before the open date.',
                param:   'open',
                depends: isAcceptSubmit
            }
        };
        var late_date_valid = {
            dateValid: true,
            dateNotBefore: {
                message: 'Please enter a date that is not before the due date.',
                param:   'due',
                depends: isAcceptSubmit
            }
        };
        var hourValid = { '12hour': true };
        var minuteValid = { minute: true };
        form.validate({
            rules: {
                markvalue: {
                    required: {
                        depends: isMarkTypeTotal
                    },
                    number: {
                        depends: isMarkTypeTotal
                    },
                    min: {
                        param: 1,
                        depends: isMarkTypeTotal
                    }
                },
                resultset: {
                    required: {
                        depends: isMarkTypeRubric
                    }
                },
                criteria: {
                    markTypeRubricCriteriaExists: {
                        depends: isMarkTypeRubric
                    },
                    markTypeRubricCriteriaCheck: {
                        depends: isMarkTypeRubric
                    }
                },
                publish_date:   publish_date_valid,
                publish_hour:   $.extend({}, hourValid, publish_date_valid),
                publish_minute: $.extend({}, minuteValid, publish_date_valid),
                publish_period: publish_date_valid,
                open_date:   open_date_valid,
                open_hour:   $.extend({}, hourValid, open_date_valid),
                open_minute: $.extend({}, minuteValid, open_date_valid),
                open_period: open_date_valid,
                due_date:    due_date_valid,
                due_hour:    $.extend({}, hourValid, due_date_valid),
                due_minute:  $.extend({}, minuteValid, due_date_valid),
                due_period:  due_date_valid,
                late_date:   late_date_valid,
                late_hour:   $.extend({}, hourValid, late_date_valid),
                late_minute: $.extend({}, minuteValid, late_date_valid),
                late_period: late_date_valid
            },
            messages: {
                resultset:   {required: 'Please select a result set'},
                open_date:   {dateNotBefore: open_date_valid.dateNotBefore.message},
                open_hour:   {dateNotBefore: open_date_valid.dateNotBefore.message},
                open_minute: {dateNotBefore: open_date_valid.dateNotBefore.message},
                open_period: {dateNotBefore: open_date_valid.dateNotBefore.message},
                due_date:    {dateNotBefore: due_date_valid.dateNotBefore.message},
                due_hour:    {dateNotBefore: due_date_valid.dateNotBefore.message},
                due_minute:  {dateNotBefore: due_date_valid.dateNotBefore.message},
                due_period:  {dateNotBefore: due_date_valid.dateNotBefore.message},
                late_date:   {dateNotBefore: late_date_valid.dateNotBefore.message},
                late_hour:   {dateNotBefore: late_date_valid.dateNotBefore.message},
                late_minute: {dateNotBefore: late_date_valid.dateNotBefore.message},
                late_period: {dateNotBefore: late_date_valid.dateNotBefore.message}
            },
            groups: {
                open_date: 'open_date open_hour open_minute open_period',
                due_date:  'due_date due_hour due_minute due_period',
                late_date: 'late_date late_hour late_minute late_period'
            },
            errorPlacement: function(label, element) {
                // Is this inside a table?
                // Put it at the bottom of the cell, otherwise usual insertAfter
                var cell = element.closest('td');
                if (cell.length > 0) {
                    label.appendTo(cell);
                } else {
                    label.insertAfter(element);
                }
            },
            // By default all hidden fields are ignored.
            // I don't want to ignore rubric values
            ignore: function(element) {
                var $element = $(element);
                if ($element.is(':hidden')) {
                    // Foundated labels:
                    // Don't ignore checkboxes or radios with visible labels
                    if ($element.closest('label:not(:hidden)').length) {
                        return false;
                    }
                    var id = element.id;
                    if (id && $('label[for="'+id+'"]:not(:hidden)').length) {
                        return false;
                    }
                    return !$element.is('[data-validate-always]');
                }
                return false;
            }
        });

        // Rubric values validation - add dynamically
        form.find('[data-role="criterion-value"]').each(function() {
            $(this).rules(
                'add',
                {
                    required: {
                        depends: isRubricValueChecked
                    },
                    digits: {
                        depends: isRubricValueChecked
                    },
                    markTypeRubricValuesCheck: {
                        depends: isRubricValueChecked
                    }
                }
            );
        });

        // Open/due/late date change triggers validation on other date fields.
        var dateInputs = form.find('input').filter('[name^="publish_"], [name^="open_"], [name^="due_"], [name^="late_"]');
        dateInputs.on('blur', function() {
            dateInputs.each(function() {
                $(this).valid();
            })
        });

        return form;
    };
}(jQuery));



