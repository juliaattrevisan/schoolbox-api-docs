var scriptValidationRules = $("script[src*=validationRules]");

/**
 * Validation rules that can be applied to the form on load
 */
var validateOptions = {
    rules: {
        useRubric: {
            require_from_group: {
                param: [1, "[data-criterion-name]"],
                depends: function (element) {
                    return element.checked;
                },
            },
        },
        title: { required: true },
        weight: {
            required: true,
            number: true,
            min: 0,
        },
        "submissionOptions[]": {
            require_from_group: {
                param: [1, "[data-type-group]"], // ensure at least one option that has data-type-group is selected
                depends: function () {
                    // only validate if accept online submissions is not none
                    return document.getElementById("acceptOnline").value !== "0";
                },
            },
        },
        "worksheet-file": {
            required: {
                depends: function () {
                    // only validate if the worksheet option is selected and then no files have been attached
                    return (
                        document.getElementById("worksheet").checked &&
                        document.getElementById("worksheet-zone").querySelector("[data-dz-previews]").children
                            .length === 0
                    );
                },
            },
        },
    },
    errorPlacement: function (error, element) {
        var $element = $(element);

        if ($element.is("[data-criterion-name]")) {
            // For validating descriptor rows - make sure error message is inserted AFTER the indicator checkbox
            error.insertAfter($element.next("label.checkbox-label"));
        } else if ($element.is("[data-group-max-value]")) {
            /* When Capability Mark Type is used, you need to provide maximum capability marks
             * There are two elements - the input, and the "Assign from indicators"
             * Spawn the error underneath these two elements
             */
            error.insertAfter($element.siblings("[data-use-criterion-values]"));
        } else if ($element.is("#useRubric")) {
            // For validating if at least one indicator is selected
            error.insertAfter($element.closest("div.switch"));
        } else if ($element.is("[data-type-group]")) {
            error.insertAfter($("#submissionType").find("label").first());

            /* For some random reason it doesn't scroll to the error it just inserted;
             * you need to go there manually. Weird
             */
            $("html, body").animate(
                {
                    scrollTop: $(".error:visible").offset().top - 60,
                },
                500,
            );
        } else {
            $.alaressValidate.defaultErrorPlacement(error, element);
        }
    },
    messages: {
        weight: "The " + scriptValidationRules.attr("data-locale-grading-proper") + " weight should be zero or greater",
        useRubric: {
            require_from_group: "You must have at least one selected indicator if using a rubric",
        },
        "submissionOptions[]": {
            require_from_group: "Please select at least one option",
        },
        "worksheet-file": "Please attach a worksheet file",
    },
};

$(document).ready(function () {
    // Initialise validator - have to do this here, as the only other place it happens is in the mage on submit
    $("#assessmentForm").alaressValidate();

    // Attach on-load rules -
    // we can't pass these as options to alaressValidate because we need to loop through elements applying rules
    attachValidationRules();

    // Attach MutationObservers and on-change listeners
    initDynamicValidationTriggers();
});

function initDynamicValidationTriggers() {
    if ($("#useRubric").is(":checked")) {
        // on change of mark range setting
        $("#useMarkRange").change(function () {
            reValidate();
        });

        // Attach listener to run monotonicity validation on a descriptor row every time one of the contained descriptor values change
        $("[data-descriptor-value]").change(function () {
            var $changedDescriptor = $(this);
            var $closestDescRow = $changedDescriptor
                .closest("[data-descriptor]")
                .prevAll("th")
                .find("[data-criterion-name]");
            $closestDescRow.valid();
        });

        var moConfig = { attributes: false, childList: true, characterData: false };

        // on change of number of levels
        var levelObserver = new MutationObserver(attachValidationRules);
        $("tr[data-group]").each(function () {
            levelObserver.observe(this, moConfig);
        });

        // on change of number of indicators/capabilities
        var indicatorObserver = new MutationObserver(attachValidationRules);
        $("table[data-rubric] tbody").each(function () {
            indicatorObserver.observe(this, moConfig);
        });

        // on change of checked indicators
        $("[data-criterion-name]").change(function () {
            var $indicatorCheckbox = $(this);
            if ($indicatorCheckbox.is(":checked")) {
                // Show any errors for the indicator itself
                $indicatorCheckbox.prev("label.error").show();

                // Show any errors for descriptors within the indicator
                $indicatorCheckbox.parents("[data-group]").find("[data-descriptor] label.error").show();
            } else {
                // Hide any errors for the indicator itself
                $indicatorCheckbox.prev("label.error").hide();

                // Hide any errors for descriptors within the indicator
                $indicatorCheckbox.parents("[data-group]").find("[data-descriptor] label.error").hide();
            }
        });
    }
}

function attachValidationRules() {
    // Don't attach rubric validation unless Use Rubric is on
    if ($("#useRubric").is(":checked")) {
        // Validate decimal places on each descriptor
        // Only validate digits (i.e. string contains only numbers, no decimal places) if mark range is on
        // Always validate it's numeric and positive
        // (although `digits` will disallow negative numbers, we are using it to validate there are no decimal places
        // only if mark range is on and giving it a fairly specific error message, so it can't be used for both)
        $("[data-descriptor-value]").each(function () {
            $(this).rules("add", {
                number: true,
                min: 0,
                digits: {
                    depends: function () {
                        return $("#useMarkRange").is(":checked");
                    },
                },
                messages: {
                    digits: "You cannot have descriptors with decimal places if using mark range",
                },
            });
        });

        // Validate all rows of descriptors
        $("[data-criterion-name]").each(function () {
            $(this).rules("add", { descriptorsAreMonotonic: true });
        });

        $("[data-group-max-value]:visible").each(function () {
            $(this).rules("add", {
                required: true,
                number: true,
                messages: {
                    number: "Please enter a numeric value",
                },
            });
        });
    }
}

function reValidate() {
    $("[data-descriptor-value]").each(function () {
        $(this).valid();
    });
    $("[data-criterion-name]").each(function () {
        $(this).valid();
    });
}

/**
 * These rules must be applied right before submit as to take into account things
 * that have been changed via JS.
 */
var beforeAttemptingSubmit = function () {
    var publishDate = $('input[name="publishDate"]'),
        publishTime = $('input[name="publishTime"]'),
        openDate = $('input[name="openDate"]'),
        openTime = $('input[name="openTime"]'),
        dueDate = $('input[name="dueDate"]'),
        dueTime = $('input[name="dueTime"]'),
        closeDate = $('input[name="closeDate"]'),
        closeTime = $('input[name="closeTime"]'),
        maxMark = $('input[name="maxMark"]');

    var openVisible = openDate.is(":visible"),
        closeVisible = closeDate.is(":visible");

    // have to do this again since its not in validateOptions and the plugin clears+reapplys rules on submit
    attachValidationRules();
    initDynamicValidationTriggers();

    if (openVisible && closeVisible) {
        openDate.rules("add", {
            required: true,
            datepickerDate: true,
            datepickerDateNotBefore: publishDate,
            messages: {
                datepickerDateNotBefore: "This should not be before the publish date above.",
            },
        });

        dueDate.rules("add", {
            datepickerDateNotBefore: openDate,
            messages: {
                datepickerDateNotBefore: "This should not be before the open date above.",
            },
        });

        closeDate.rules("add", {
            required: true,
            datepickerDateNotBefore: dueDate,
            messages: {
                datepickerDateNotBefore: "This should not be before the due date above.",
            },
        });

        openTime.rules("add", {
            required: true,
            timepickerTime: true,
        });

        // check the times aren't earlier than the one above (if the dates are the same)
        if (openDate.val() == publishDate.val()) {
            openTime.rules("add", {
                timepickerNotBefore: publishTime,
                messages: {
                    timepickerNotBefore: "This should not be before the publish time above.",
                },
            });
        }

        if (dueDate.val() == openDate.val()) {
            dueTime.rules("add", {
                timepickerNotBefore: openTime,
                messages: {
                    timepickerNotBefore: "This should not be before the open time above.",
                },
            });
        }

        closeTime.rules("add", {
            required: true,
            timepickerTime: true,
        });

        if (closeDate.val() == dueDate.val()) {
            closeTime.rules("add", {
                timepickerNotBefore: dueTime,
                messages: {
                    timepickerNotBefore: "This should not be before the due time above.",
                },
            });
        }
    } else {
        dueDate.rules("add", {
            datepickerDateNotBefore: publishDate,
            messages: {
                datepickerDateNotBefore: "This should not be before the publish date above.",
            },
        });

        // if the dates are the same - need to check due time isn't before publish time
        if (dueDate.val() == publishDate.val()) {
            dueTime.rules("add", {
                timepickerNotBefore: publishTime,
                messages: {
                    timepickerNotBefore: "This should not be before the publish time above.",
                },
            });
        }
    }

    // Validate Total Available Marks on form submit if field is visible (only visible if using Total Mark type)
    // NOTE: not to be confused with capability max!
    // This is the total marks for the ENTIRE due work, which gets summed from cap maxes
    if (maxMark.is(":visible")) {
        // The message is a little different when rubrics are in use,
        // because the solution is a little different.
        var messageCallback = function () {
            if ($('input[name="useRubric"]').is(":checked")) {
                return "If using Total Mark with rubrics, at least one descriptor must have a points value greater than zero.";
            }
            return "This field must be a number greater than zero.";
        };

        maxMark.rules("add", {
            required: true,
            number: true,
            greaterThanZero: true,
            messages: {
                required: messageCallback,
                number: messageCallback,
                greaterThanZero: messageCallback,
            },
        });
    }
};
