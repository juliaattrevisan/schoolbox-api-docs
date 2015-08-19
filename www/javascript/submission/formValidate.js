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

        // Do assessment-specific descriptors get shown?
        var isRubricAssessmentSpecificDescriptors = function() {
            return document.getElementById('rs-0').checked;
        };

        // validation rules
        var marktype = {
            total:  form.find('[data-hide-watch-value="raw"]'),
            rubric: form.find('[data-hide-watch-value="rubric"]'),
            letter: form.find('[data-hide-watch-value="letter"]')
        };

        //// Total mark validator
        //$.validator.addMethod("markTypeTotalCheck", function(value) {
        //    if (marktype.total.is(':visible')) {
        //        return value > 0;
        //    } else {
        //        return true;
        //    }
        //}, "Please enter a numeric mark amount");

        // Rubric validator: do any exist?
        $.validator.addMethod('markTypeRubricCriteriaExists', function() {
            if (marktype.rubric.is(':visible')) {
                return rubricCriteria.find('input[type="checkbox"]').length >= 1;
            } else {
                return true;
            }
        }, 'Please ensure at least one criterion exists');

        // Rubric criteria: is at least one selected?
        $.validator.addMethod('markTypeRubricCriteriaCheck', function() {
            if (marktype.rubric.is(':visible')) {
                return rubricCriteria.find('input:checked').length >= 1;
            } else {
                return true;
            }
        }, 'Please select at least one criterion');

        //// Rubric result set: is one selected?
        //// (Simplify to required: resultset, depends: rubric is visible)
        //$.validator.addMethod('markTypeRubricResultsetCheck', function() {
        //    if(marktype.rubric.is(':visible')) {
        //        return $('#rubric-results').find('input:checked').length == 1;
        //    }
        //    else {
        //        return true;
        //    }
        //}, 'Please select a result set');

        // Are rubric values checked?
        var isRubricValueChecked = function(element) {
            if (!marktype.rubric.is(':visible')) {
                return false;
            }
            if (!isRubricAssessmentSpecificDescriptors()) {
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
                var criterion       = element.closest('[data-role="criterion"]');
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

        var isAcceptSubmit  = function() {
            return acceptSubmit.is(':checked');
        };
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
                    required: true,
                    number: true,
                    min: 1
                },
                result_set: {
                    required: true
                    //{
                    //    depends: function() {
                    //        return marktype.rubric.is(':visible');
                    //    }
                    //}
                },
                criteria: {
                    markTypeRubricCriteriaExists: true,
                    markTypeRubricCriteriaCheck: true
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
                result_set:  {required: 'Please select a result set'},
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
            ignore: ':hidden:not([data-role="criterion-value"])'
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

        //
        // Open/due/late date change triggers validation on other date fields.
        form.find('input')
            .filter('[name^="open_"], [name^="due_"], [name^="late_"]')
            .blur(
            function() {
                form.find('input[name="open_date"]').valid();
                form.find('input[name="due_date"]').valid();
                form.find('input[name="late_date"]').valid();
            }
        );

        return form;
    };
}(jQuery));



