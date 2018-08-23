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
    }
};


function initDecimalDescsValidation() {
    function reValidate() {
        console.log("Revalidating");
        $("[data-descriptor-value]").each(function() {
            $(this).valid();
        });
    }
    function attachValidationRules() {
        // Validate decimal places on each descriptor
        $("[data-descriptor-value]").each(function() {
            $(this).rules("add", { noDecimalPlacesWithMarkRange: true });
        })
    }


    // on load
    attachValidationRules();

    // on change of mark range setting
    $("#useMarkRange").change(function() {
        reValidate();
    });

    // on change of number of levels
    var levelObserver = new MutationObserver(attachValidationRules);
    var config = { attributes: false, childList: true, characterData: false };
    $("tr[data-group]").each(function() {
        levelObserver.observe(this, config);
    });


    // TODO on change of number of checked indicators?

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

    // Validate each row of descriptors
    $("[data-descriptor-value]").each(function() {
        $(this).rules("add",
            {
                descriptorsAreMonotonic: true,
                messages: { // TODO why this not working
                    descriptorsAreMonotonic: " use only descriptor points with non-decimal values if Use Mark Range is enabled"
                }
            }
        );
    });


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
