(function ($) {
    "use strict";

    $.alaressValidate = {};

    /**
     * Gets a dayjs date given a format and date.
     *
     * @param format the MomentJS format to get this date in.
     * @param date   the date string
     * @param hour   the hour to set
     * @param minute the minute to set
     * @param second the second to set
     * @returns {dayjs}
     */
    var getDateFromString = function (format, date, hour, minute, second) {
        return dayjs(date, format).hour(hour).minute(minute).second(second);
    };

    /**
     * Get a 24-hour time representation of a date field
     *
     * @param date_field
     * @param form
     * @returns {dayjs}
     */
    var getNormalizedDate = function (date_field, form) {
        var field_name = date_field;
        var element = null;
        if (typeof date_field === "object") {
            field_name = $(date_field).attr("name").replace(/_\w+$/, "");
            element = $(date_field).closest("form");
        } else {
            element = $(form);
        }
        var dateInput = element.find(':input[name="' + field_name + '_date"]');
        var date = dateInput.val();
        var hour = element.find(':input[name="' + field_name + '_hour"]').val();
        var minute = element.find(':input[name="' + field_name + '_minute"]').val();
        var meridian = element.find(':input[name="' + field_name + '_period"]').val();

        if (hour.length) {
            //
            // Convert time to 24 hours
            switch (meridian.toString().toLowerCase()) {
                case "am":
                    // 12am becomes 0
                    if (hour === "12") {
                        hour = 0;
                    }
                    break;

                case "pm":
                    // unless 12, add 12
                    if (hour !== "12") {
                        hour = Number(hour) + 12;
                    }
                    break;

                default:
                    break;
            }
            hour = Number(hour);
        } else {
            // Use default hour and minute from the default_time data attribute
            // if it's there.
            hour = 0;
            minute = 0;
            var defaultTime = dateInput.data("default_time");
            if (defaultTime.match) {
                var split = dateInput.data("default_time").match(/^(\d+):(\d+)$/);
                if (split) {
                    hour = split[1];
                    minute = split[2];
                }
            }
        }

        return getDateFromString(dateDisplayFormats.dayjs, date, hour, minute, 0);
    };

    /**
     * Custom email validation method: allow any non-ASCII character
     */
    $.validator.methods.email = function (value, element) {
        if (this.optional(element)) {
            return true;
        }
        // The "official" email validation regexp from
        // https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address,
        // extended to allow support for multibyte characters.
        // Basic matches: letters, numbers, any non-ASCII char
        const letterClass = "a-zA-Z0-9\\P{ASCII}";
        const emailRegexp = new RegExp(
            `^[${letterClass}.!#$%&'*+\\/=?^_\`{|}~-]+@[${letterClass}](?:[${letterClass}-]{0,61}[${letterClass}])?(?:\\.[${letterClass}](?:[${letterClass}-]{0,61}[${letterClass}])?)*$`,
            "u",
        );
        return value.match(emailRegexp);
    };

    /**
     * Validator for a datepicker (format: local date  | local datetime)
     */
    $.validator.addMethod(
        "datepickerDate",
        function (value, element) {
            var datepickerMoment;

            // If the field is date only or allday event value is on
            if (
                typeof $(element).attr("data-datepicker-time") == "undefined" ||
                $("#" + $(element).attr("data-datepicker-alldayinput")).val() == 1
            ) {
                datepickerMoment = dayjs(value, dateDisplayFormats.dayjs, true); // Date
            } else {
                datepickerMoment = dayjs(value, dateDisplayFormats.dayjsDatetime, true); // Datetime
            }

            return datepickerMoment.isValid();
        },
        "Please enter a valid date",
    );

    /**
     * Extension of datepickerDate for checking that the date is not earlier
     * than another element's date.
     */
    $.validator.addMethod(
        "datepickerDateNotBefore",
        function (value, element, other_element_selector) {
            var date = getDateFromString(dateDisplayFormats.dayjs, value, 0, 0, 0);
            var other_date = getDateFromString(dateDisplayFormats.dayjs, other_element_selector.val(), 0, 0, 0);

            // If the other date is empty or not valid, return valid for this and leave the error for that field for now
            // Otherwise we end up with an error message on this field when the user is still working on the other one
            if (!other_date.isValid()) {
                return true;
            }

            return date.isValid() && date.isSameOrAfter(other_date);
        },
        "Please enter a date that is <i>after</i> the previous date",
    );

    /**
     * Validator for a timepicker (format: h:mma)
     */
    $.validator.addMethod(
        "timepickerTime",
        function (value, element) {
            return dayjs(value, "h:mma", true).isValid();
        },
        "Please enter a valid time",
    );

    /**
     * timepickerTime rule - checks that the field's time is not earlier
     * than another one.
     */
    $.validator.addMethod(
        "timepickerNotBefore",
        function (value, element, other_element_selector) {
            var time = dayjs(value, "h:mma", true);
            var otherTime = dayjs(other_element_selector.val(), "h:mma", true);
            return time.isAfter(otherTime);
        },
        "Please enter a valid time",
    );

    /**
     * Date-time validation method - works with a time selector as well, and
     * uses some guesswork to find the datetime fields.
     *
     * It's used on the submission create field.
     */
    $.validator.addMethod(
        "dateValid",
        function (value, element) {
            return getNormalizedDate(element).isValid();
        },
        "Please enter a valid date",
    );

    /**
     * dateValid extension - checks that this field's date is not earlier than
     * than another one.
     */
    $.validator.addMethod(
        "dateNotBefore",
        function (value, element, other_date_field) {
            var form = $(element).closest("form");
            return getNormalizedDate(element).isAfter(getNormalizedDate(other_date_field, form));
        },
        "Please enter a date that is after",
    );

    /**
     * Hour and minute validation rules
     */
    //$.validator.addClassRules('12hour', { range: [1, 12] });
    //$.validator.addClassRules('24hour', { range: [0, 23] });
    //$.validator.addClassRules('minute', { range: [0, 59] });
    $.validator.addMethod(
        "12hour",
        function (value, element) {
            return (
                $.validator.methods.digits.call(this, value, element) &&
                $.validator.methods.range.call(this, value, element, [1, 12])
            );
        },
        "Please enter a valid hour",
    );
    $.validator.addMethod(
        "24hour",
        function (value, element) {
            return (
                $.validator.methods.digits.call(this, value, element) &&
                $.validator.methods.range.call(this, value, element, [0, 23])
            );
        },
        "Please enter a valid hour",
    );
    $.validator.addMethod(
        "minute",
        function (value, element) {
            return (
                $.validator.methods.digits.call(this, value, element) &&
                $.validator.methods.range.call(this, value, element, [0, 59])
            );
        },
        "Please enter a valid minute",
    );

    /**
     * selected extension - checks that you've selected something.
     */
    $.validator.addMethod(
        "selected",
        function (value, element, other_date_field) {
            if (this.optional(element)) {
                return true;
            }

            return $(element).find(":selected[value]").length > 0;
        },
        "Please select a value",
    );

    /**
     * isURL extension - checks that what you've entered is a HTTP, HTTPS, FTP
     * or relative URL.
     *
     * Should match a (possible full) subset of values permitted in library/Url.php::isValidUrl()
     */
    $.validator.addMethod(
        "isURL",
        function (value, element) {
            if (this.optional(element)) {
                return true;
            }

            var regExURL = new RegExp(
                '^((((http)|(ftp))s?://[\\w-]+(\\.[\\w-]+)*(:[0-9]+)?(/\\S*)?)|([/#?]\\S*))$|^mailto:(([^<>()\\[\\]\\\\.,;:\\s@"]+(\\.[^<>()\\[\\]\\\\.,;:\\s@"]+)*)|(".+"))@((\\[[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}])|(([a-zA-Z\\-0-9]+\\.)+[a-zA-Z]{2,}))$',
            );

            return regExURL.test(value.trim().toLowerCase());
        },
        "Please enter a URL beginning with http://, https://, ftp://, mailto:, or a relative URL (beginning with '/', '#', or '?')",
    );

    /**
     * equalsOther extension - checks that the value of this field equals that of some other field.
     */
    $.validator.addMethod(
        "equalsOtherField",
        function (value, element, otherElement) {
            return $(element).val() == $(otherElement).val();
        },
        "Values do not match",
    );

    /**
     * oneCheckbox extension - checks that at least one checkbox has been selected in a group
     * Set a checkbox as in a group with data-checkbox-group="GROUPNAME"
     */
    $.validator.addMethod(
        "oneCheckbox",
        function (value, element, checkboxGroup) {
            return $('input[data-checkbox-group="' + checkboxGroup + '"]:checked').length > 0;
        },
        "You must select at least one option below",
    );

    /**
     * CKEditor doesn't update the textarea until after the form is checked
     * so getData() on the instance and check that
     */
    $.validator.addMethod(
        "ckeRequired",
        function (value, element) {
            var textboxId = $(element).attr("id");
            return $.trim($(window.sbProps.editors[textboxId].getData())).length > 0;
        },
        "This field is required",
    );

    /**
     * Check if a required field is blank/contains only whitespace
     */
    $.validator.addMethod(
        "requiredNotBlank",
        function (value, element) {
            /**
             * Call the Required method first, which displays an error message
             * only if field is completely blank (no whitespace or other chars).
             * We are doing this in order to display a different error message
             * depending on whether the field contains any chars or not.
             */
            jQuery.validator.methods.required.call(this, value, element);

            return $.trim(value).length;
        },
        "This field cannot be blank",
    );

    /**
     * Alphanumeric
     */
    $.validator.addMethod(
        "alphanumeric",
        function (value, element) {
            return this.optional(element) || /^[a-z0-9]+$/i.test(value);
        },
        "This field must only contain letters or numbers.",
    );

    /**
     * Greater than zero
     */
    $.validator.addMethod(
        "greaterThanZero",
        function (value, element) {
            return this.optional(element) || parseFloat(value) > 0;
        },
        "Amount must be greater than zero",
    );

    /**
     * Validate email while ignoring whitespace-only values
     */
    $.validator.addMethod(
        "emailTrimmed",
        function (value, element) {
            // If field contains just whitespace...pass
            if ($.trim(value).length < 1) {
                return true;
            }
            return jQuery.validator.methods.email.call(this, $.trim(value), element);
        },
        "Please enter a valid email address",
    );

    /**
     * Check whether $value is in a json-encoded $list, or array $list
     * It assumes that $list is a json object if not an array
     */
    $.validator.addMethod(
        "notInList",
        function (value, element, list) {
            value = $.trim(value);

            if (!Array.isArray(list)) {
                list = JSON.parse(list);
            }

            return list.indexOf($.trim(value)) < 0;
        },
        "This heading is already in use - please choose another.",
    );

    /**
     * Check whether a slug is in use or not
     */
    $.validator.addMethod(
        "notInSlugList",
        function (value, element, list) {
            value = $.trim(value).toLowerCase();

            if (!Array.isArray(list)) {
                list = JSON.parse(list);
            }

            //This simulates the preg replace in /schoolbox/library/Url.php::getSlug()
            var pattern = /[^a-zA-Z\d\s\-\+\/]*/g;
            var replace = "";

            var newValue = value.replace(pattern, replace);

            pattern = /(\s|-|\/)+/g;
            replace = "-";

            newValue = newValue.replace(pattern, replace);

            return list.indexOf($.trim(newValue)) < 0;
        },
        "This heading is already in use - please choose another.",
    );

    /**
     * Used in rubric to ensure descriptors's values sequentially ascend, or descend, per indicator
     */
    $.validator.addMethod(
        "descriptorsAreMonotonic",
        function (value, indicatorCheckbox) {
            var valid = true;

            if ($("#useMarkRange").is(":checked")) {
                var descriptorList = $.makeArray(
                    $(indicatorCheckbox).closest("tr[data-group]").find("[data-descriptor-value]"),
                );

                var hasDescValue = function (descriptor) {
                    // Value contains digits
                    return $(descriptor).val().match(/\d/);
                };

                // Remove any descriptors from the list that don't have values
                // since value-less descriptors are permitted in amongst valued descriptors
                // then simplify array to just the desc values
                var allDescriptorsWithValues = descriptorList.filter(hasDescValue).map(function (descriptor) {
                    return $(descriptor).val();
                });

                var prevVal,
                    prevDiff = null;
                $(allDescriptorsWithValues).each(function (index, currentDescriptorValue) {
                    // Not the first item?
                    if (null !== prevVal) {
                        var diff = currentDescriptorValue - prevVal;

                        // No increase/decrease ?
                        if (0 === diff) {
                            valid = false;
                        }

                        // Do we have a prev diff to compare against?
                        // Accounts for both ascending and descending possibilities
                        if (null !== prevDiff && ((prevDiff > 0 && diff < 0) || (prevDiff < 0 && diff > 0))) {
                            valid = false;
                        }
                        prevDiff = diff;
                    }
                    prevVal = currentDescriptorValue;
                });
            }

            return valid;
        },
        "Descriptor values must be an ascending or descending sequence",
    );

    /**
     * Default error placement method.
     *
     * First check for the attribute data-has-error-placeholder, in which case insert into an .error-placeholder div
     * Then check for standard checkbox or radio, in which case insert error before the set of checkboxes/radios
     * In all other situations place the error after the element.
     *
     * @param error
     * @param element
     */
    $.alaressValidate.defaultErrorPlacement = function (error, element) {
        if (element.parents("fieldset").is("[data-has-error-placeholder]")) {
            element.parents("fieldset").find("div.error-placeholder").html(error);
        } else if (element.closest(".input-group").length) {
            error.insertAfter(element.closest(".input-group"));
        } else if (element[0].selectize) {
            // Is this a selectize? Insert the error beneath the control
            error.insertAfter(element[0].selectize.$control);
        } else if (element.is('[type="checkbox"], [type="radio"]')) {
            var parentSearch;

            // If element has a .checklist or .radiolist parent - use that to search for the first input
            if (element.closest(".checklist, .radiolist").length) {
                parentSearch = ".checklist, .radiolist";
            } else {
                // Otherwise use the closest div or fieldset parent
                parentSearch = "div, fieldset";
            }

            // This used to use the :first-of-type pseudoclass selector (see https://api.jquery.com/first-of-type-selector/)
            // However for some reason it would not select anything if the closest parent had
            // multiple input types e.g. an input[type=text] alongside an input[type=checkbox].
            // get(0) does the same thing using jquery instead of css selectors, and doesn't break.
            error.insertBefore(element.closest(parentSearch).find("input[type=checkbox], input[type=radio]").get(0));
        } else {
            error.insertAfter(element);
        }
    };

    /**
     * Explode on the slash to determine numerator/denominator
     * Unfortunately this replicates backend logic
     * Both parts must be numeric
     * Numerator must be at least 0 otherwise it's an illegal attribution
     */
    jQuery.validator.addMethod("withinRange", function (value, element) {
        /* Null/empty setting is permitted
         * Also, SA-1489 is a thing which might make this a bit deceiving
         */
        if (null == $(element).val() || "" === $(element).val()) {
            return true;
        }

        /* Is it one of the Total Mark string format?
         * If so, interpret it differently than a Submission State
         */
        var parts = $(element).val().split("/");

        /* Trim perhaps-adjacent spaces to test for numeric representation
         * This rule might also be used for percentage, so remove that symbol too
         */
        parts[0] = $.trim(parts[0].replace("%", ""));

        if ($.isNumeric(parts[0])) {
            var numerator = parseInt(parts[0]);
            var denominator = $(element).attr("data-validation-limit");

            return numerator >= 0 && numerator <= denominator;
        }

        /* Otherwise it could be one of the submission states like Absent or Not Submitted
         * so test that it's one of the available customisable options
         */
        var val = $(element).val();

        return (
            $(element)
                .parents(".combobox")
                .find(".combobox_selector li")
                .filter(function () {
                    return $(this).text() === val;
                }).length > 0
        );
    });

    /**
     * Explode on the slash to determine numerator/denominator
     * Unfortunately this replicates backend logic
     * Value must be at least 0 otherwise it's an illegal attribution
     */
    jQuery.validator.addMethod("withinPercentageRange", function (value, element) {
        /* Null/empty setting is permitted
         * Also, SA-1489 is a thing which might make this a bit deceiving
         */
        if (null == $(element).val() || "" === $(element).val()) {
            return true;
        }

        /* Is it a Percentage string format?
         * If so, interpret it differently than a Submission State
         */
        var val = $(element).val();

        /* Trim perhaps-adjacent spaces to test for numeric representation
         * This rule might also be used for percentage, so remove that symbol too
         */
        val = $.trim(val.replace("%", ""));

        if ($.isNumeric(val)) {
            var numerator = parseInt(val);
            var denominator = $(element).attr("data-validation-limit");

            return numerator >= 0 && numerator <= denominator;
        }

        /* Otherwise it could be one of the submission states like Absent or Not Submitted
         * so test that it's one of the available customisable options
         */
        return (
            $(element)
                .parents(".combobox")
                .find(".combobox_selector li")
                .filter(function () {
                    return $(this).text() === val;
                }).length > 0
        );
    });

    /**
     * Unfortunately this replicates backend logic
     * Letter Grades, though similar to other types, are distinct due to <option> structure in a select
     * It's not just some random unordered list
     */
    jQuery.validator.addMethod("withinLetterGradeRange", function (value, element) {
        /* Null/empty setting is permitted
         */
        if (null == $(element).val() || "" === $(element).val()) {
            return true;
        }

        /* A previously-legal value that is no longer legal
         */
        if ($(element).val() == -1) {
            return false;
        }

        /* Otherwise it could be one of the submission states like Absent or Not Submitted
         * so test that it's one of the available customisable options
         */
        var val = $(element).val();

        return (
            $(element)
                .find("option")
                .filter(function () {
                    return $(this).val() === val;
                }).length > 0
        );
    });

    /**
     * Unified interface for form validation
     *
     * @param options
     */
    $.fn.alaressValidate = function (options) {
        var defaults = {
            // Default to not ignoring validation on any fields, even if hidden
            ignore: false,
            ignoreTitle: true,
            errorPlacement: $.alaressValidate.defaultErrorPlacement,
            submitHandler: function (form) {
                // Find the submit button for this form and disable it,
                // to avoid accidental multiple requests
                $(form).find("input[type=submit]").prop("disabled", true).val("Working...");
                $(form).find("button[type=submit]").prop("disabled", true).text("Working...");

                // ..and submit the form
                form.submit();
            },
        };
        var config = $.extend({}, defaults, options || {});
        return this.validate(config);
    };
})(jQuery);
