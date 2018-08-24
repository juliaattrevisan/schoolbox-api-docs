
$.validator.addMethod('descriptorsAreMonotonic', function(value, indicatorCheckbox) {
    var descriptorList = $.makeArray($(indicatorCheckbox).closest("tr[data-group]").find("[data-descriptor-value]"));

    function hasDescValue(descriptor) {
        // Value contains digits
        return $(descriptor).val().match(/\d/);
    }

    // Remove any descriptors from the list that don't have values
    // since value-less descriptors are permitted in amongst valued descriptors
    // then simplify array to just the desc values
    var allDescriptorsWithValues = descriptorList.filter(hasDescValue).map(function (descriptor) {return $(descriptor).val();});
    console.log(allDescriptorsWithValues);

    var prevVal, prevDiff = null;
    $(allDescriptorsWithValues).each(function(index, currentDescriptorValue) {
        console.log(currentDescriptorValue);
        // Not the first item?
        if(null !== prevVal){
            var diff = currentDescriptorValue - prevVal;

            // No increase/decrease ?
            if(0 === diff){
                return false;
            }

            // Do we have a prev diff to compare against?
            // Accounts for both ascending and descending possibilities
            if(null !== prevDiff && ((prevDiff > 0 && diff < 0) || (prevDiff < 0 && diff > 0))) {
                return false;
            }
            prevDiff = diff;
        }
        prevVal = currentDescriptorValue;
    });

    return true;
    // return false;
}, "Descriptor values must be an ascending or descending sequence");



var scriptValidationRules = $('script[src*=validationRules]');

/**
 * Validation rules that can be applied to the form on load
 */
var validateOptions = {
    rules: {
        title: {required: true},
        weight: {
            required: true,
            number: true,
            min: 0
        },
    },
    messages: {
        weight: 'The ' + scriptValidationRules.attr('data-locale-grading-proper') + ' weight should be zero or greater',
    },
    errorPlacement: function(error, element) {
        if ($(element).attr("data-criterion-name")) {
            error.insertAfter($(element).next("label.checkbox-label"));
        } else {
            error.insertAfter(element);
        }
    }
};

function initDecimalDescsValidation() {

    function attachValidationRules() {
        // Validate decimal places on each descriptor
        $("[data-descriptor-value]").each(function() {
            $(this).rules("add", { noDecimalPlacesWithMarkRange: true });
        });

        // Validate all rows of descriptors
        $("[data-criterion-name]").each(function() {
            $(this).rules("add", { descriptorsAreMonotonic: true });
        });
    }

    function reValidate() {
        console.log("Revalidating");
        $("[data-descriptor-value]").each(function() {
            $(this).valid();
        });
        $("[data-criterion-name]").each(function() {
            $(this).valid();
        });
    }

    // on load
    attachValidationRules();

    // on change of mark range setting
    $("#useMarkRange").change(function() {
        reValidate();
    });


    // Attach listener to run monotonicity validation on a descriptor row every time one of the contained descriptor values change
    $("[data-descriptor-value]").change(function() {
        var $changedDescriptor = $(this);
        var $closestDescRow = $changedDescriptor.closest("[data-descriptor]").prevAll("th").find("[data-criterion-name]");
        $closestDescRow.valid();
    });


    var moConfig = { attributes: false, childList: true, characterData: false };

    // on change of number of levels
    var levelObserver = new MutationObserver(attachValidationRules);
    $("tr[data-group]").each(function() {
        levelObserver.observe(this, moConfig);
    });

    // on change of number of indicators/capabilities
    var indicatorObserver = new MutationObserver(attachValidationRules);
    $("table[data-rubric] tbody").each(function() {
        indicatorObserver.observe(this, moConfig);
    });

    // on change of checked indicators
    // TODO maybe try using depends instead of this?
    $("[data-criterion-name]").change(function() {
        var $indicatorCheckbox = $(this);
       if ($indicatorCheckbox.is(":checked")) {
           $("[data-descriptor]").each(function() {
               $(this).find("label.error").show();
           });
       } else {
           $("[data-descriptor]").each(function() {
               $(this).find("label.error").hide();
           });
       }
    });
}


/**
 * These rules must be applied right before submit as to take into account things
 * that have been changed via JS.
 */
var beforeSubmit = function() {
    var publishDate = $('input[name="publishDate"]'),
        publishTime = $('input[name="publishTime"]'),
        openDate = $('input[name="openDate"]'),
        openTime = $('input[name="openTime"]'),
        dueDate = $('input[name="dueDate"]'),
        dueTime = $('input[name="dueTime"]'),
        closeDate = $('input[name="closeDate"]'),
        closeTime = $('input[name="closeTime"]'),
        maxMark = $('input[name="maxMark"]');

    var openVisible = openDate.is(':visible'),
        closeVisible = closeDate.is(':visible');

    // have to do this again since its not in validateOptions and the plugin clears+reapplys rules on submit
    initDecimalDescsValidation();

    if (openVisible && closeVisible) {

        openDate.rules('add', {
            required: true,
            datepickerDate: true,
            datepickerDateNotBefore: publishDate,
            messages: {
                datepickerDateNotBefore: 'This should not be before the publish date above.'
            }
        });

        dueDate.rules('add', {
            datepickerDateNotBefore: openDate,
            messages: {
                datepickerDateNotBefore: 'This should not be before the open date above.'
            }
        });

        closeDate.rules('add', {
            required: true,
            datepickerDateNotBefore: dueDate,
            messages: {
                datepickerDateNotBefore: 'This should not be before the due date above.'
            }
        });

        openTime.rules('add', {
            required: true,
            timepickerTime: true
        });

        // check the times aren't earlier than the one above (if the dates are the same)
        if (openDate.val() == publishDate.val()) {
            openTime.rules('add', {
                timepickerNotBefore: publishTime,
                messages: {
                    timepickerNotBefore: 'This should not be before the publish time above.'
                }
            });
        }

        if (dueDate.val() == openDate.val()) {
            dueTime.rules('add', {
                timepickerNotBefore: openTime,
                messages: {
                    timepickerNotBefore: 'This should not be before the open time above.'
                }
            });
        }

        closeTime.rules('add', {
            required: true,
            timepickerTime: true

        });

        if (closeDate.val() == dueDate.val()) {
            closeTime.rules('add', {
                timepickerNotBefore: dueTime,
                messages: {
                    timepickerNotBefore: 'This should not be before the due time above.'
                }
            });
        }

    } else {

        dueDate.rules('add', {
            datepickerDateNotBefore: publishDate,
            messages: {
                datepickerDateNotBefore: 'This should not be before the publish date above.'
            }
        });

        // if the dates are the same - need to check due time isn't before publish time
        if (dueDate.val() == publishDate.val()) {
            dueTime.rules('add', {
                timepickerNotBefore: publishTime,
                messages: {
                    timepickerNotBefore: 'This should not be before the publish time above.'
                }
            });
        }

    }

    // Max mark validation: only when the field is visible
    if (maxMark.is(':visible')) {

        // The message is a little different when rubrics are in use,
        // because the solution is a little different.
        var messageCallback = function() {
            if ($('input[name="useRubric"]').is(':checked')) {
                return 'If using Total Mark with rubrics, at least one descriptor must have a points value greater than zero.'
            }
            return 'This field must be a number greater than zero.';
        };

        maxMark.rules('add', {
            required: true,
            number: true,
            greaterThanZero: true,
            messages: {
                required: messageCallback,
                number: messageCallback,
                greaterThanZero: messageCallback
            }
        });
    }

};
