/**
 * Custom extensions for jQuery validate plugin.
 */
(function($) {
    var getNormalizedDate = function(date_field, form) {
        var field_name = date_field;
        var element    = null;
        if (typeof date_field === 'object') {
            field_name = $(date_field).attr('name').replace(/_\w+$/, '');
            element    = $(date_field).closest('form');
        }
        else {
            element = $(form);
        }
        var dateInput = element.find(':input[name="'+field_name+'_date"]');
        var date      = dateInput.val();
        var hour      = element.find(':input[name="'+field_name+'_hour"]').val();
        var minute    = element.find(':input[name="'+field_name+'_minute"]').val();
        var meridian  = element.find(':input[name="'+field_name+'_period"]').val();

        if (hour.length) {
            //
            // Convert time to 24 hours
            switch (meridian.toString().toLowerCase()) {
                case 'am':
                    // 12am becomes 0
                    if (hour === '12') {
                        hour = 0;
                    }
                    break;

                case 'pm':
                    // unless 12, add 12
                    if (hour !== '12') {
                        hour = Number(hour) + 12;
                    }
                    break;

                default:
                    break;
            }
            hour = Number(hour);
        }
        else {
            // Use default hour and minute from the default_time data attribute
            // if it's there.
            hour   = 0;
            minute = 0;
            var defaultTime = dateInput.data('default_time');
            if (defaultTime.match) {
                var split = dateInput.data('default_time').match(/^(\d+):(\d+)$/);
                if (split) {
                    hour   = split[1];
                    minute = split[2];
                }
            }
        }

        return getDateFromString(dateDisplayFormats.momentJs, date, hour, minute, 0)
    }


    /**
     * Gets a momentJS date given a format and date.
     *
     * @param format the MomentJS format to get this date in.
     * @param date   the date string
     * @param hour   the hour to set
     * @param minute the minute to set
     * @param second the second to set
     * @returns {moment}
     */
    var getDateFromString = function(format, date, hour, minute, second) {
        return moment(date, format)
            .hour(hour)
            .minute(minute)
            .second(second)
        ;
    }


    /**
     * Validator for a datepicker (format: dd/mm/yyyy)
     */
    $.validator.addMethod('datepickerDate', function(value, element) {
        return getDateFromString(dateDisplayFormats.momentJs, value, 0, 0, 0).isValid();
    }, 'Please enter a valid date');


    /**
     * Extension of datepickerDate for checking that the date is not earlier
     * than another element's date.
     */
    $.validator.addMethod('datepickerDateNotBefore', function(value, element, other_element_selector) {
        var date       = getDateFromString(dateDisplayFormats.momentJs, value, 0, 0, 0);
        var other_date = getDateFromString(dateDisplayFormats.momentJs, other_element_selector.val(), 0, 0, 0);
        return date.isValid()
            && other_date.isValid()
            && date.valueOf() >= other_date.valueOf()
        ;
    }, 'Please enter a valid date');


    /**
     * Validator for a timepicker (format: h:mma)
     */
    $.validator.addMethod('timepickerTime', function(value, element) {
        return moment(value, 'h:mma', true).isValid();
    }, 'Please enter a valid time');


    /**
     * timepickerTime rule - checks that the field's time is not earlier
     * than another one.
     */
    $.validator.addMethod('timepickerNotBefore', function(value, element, other_element_selector) {

        var time = moment(value, 'h:mma', true);
        var otherTime = moment(other_element_selector.val(), 'h:mma', true);
        return time.valueOf() >= otherTime.valueOf();
    }, 'Please enter a valid time');


    /**
     * Date-time validation method - works with a time selector as well, and
     * uses some guesswork to find the datetime fields.
     *
     * It's used on the submission create field.
     */
    $.validator.addMethod('dateValid', function(value, element) {
        return getNormalizedDate(element).isValid();
    }, 'Please enter a valid date');


    /**
     * dateValid extension - checks that this field's date is not earlier than
     * than another one.
     */
    $.validator.addMethod('dateNotBefore', function(value, element, other_date_field) {
        var item = $(element);
        var form = item.closest('form');
        return getNormalizedDate(element).valueOf() >= getNormalizedDate(other_date_field, form).valueOf();
    }, 'Please enter a date that is after');


    /**
     * Hour and minute validation rules
     */
    //$.validator.addClassRules('12hour', { range: [1, 12] });
    //$.validator.addClassRules('24hour', { range: [0, 23] });
    //$.validator.addClassRules('minute', { range: [0, 59] });
    $.validator.addMethod(
        '12hour',
        function(value, element) {
            return $.validator.methods.digits.call(this, value, element)
                && $.validator.methods.range.call(this, value, element, [1, 12]);
        },
        'Please enter a valid hour'
    );
    $.validator.addMethod(
        '24hour',
        function(value, element) {
            return $.validator.methods.digits.call(this, value, element)
                && $.validator.methods.range.call(this, value, element, [0, 23]);
        },
        'Please enter a valid hour'
    );
    $.validator.addMethod(
        'minute',
        function(value, element) {
            return $.validator.methods.digits.call(this, value, element)
                && $.validator.methods.range.call(this, value, element, [0, 59]);
        },
        'Please enter a valid minute'
    );


    /**
     * selected extension - checks that you've selected something.
     */
    $.validator.addMethod('selected', function(value, element, other_date_field) {
        if (this.optional(element)) {
            return true
        }
        var selected = $(element).find(':selected[value]')
        return (selected.length > 0)
    }, 'Please select a value');


    /**
     * isURL extension - checks that what you've entered is a HTTP, HTTPS, FTP
     * or relative URL.
     * Should match a (possible full) subset of values permitted in library/Url.php::isValidUrl()
     */
    var regExURL = new RegExp("^((((http)|(ftp))s?://[\\w-]+(\\.[\\w-]+)*(/\\S*)?)|([/#?]\\S*))$");
    $.validator.addMethod("isURL", function (value, element) {
        if (this.optional(element)) {
            return true;
        }
        return regExURL.test(value.trim().toLowerCase());
    }, "Please enter a URL beginning with http://, https://, ftp://, or a relative URL (beginning with '/', '#', or '?')");


    /**
     * equalsOther extension - checks that the value of this field equals that of some other field.
     */
    $.validator.addMethod('equalsOtherField', function(value, element, otherElement) {
        return $(element).val() == $(otherElement).val();
    }, 'Values do not match');


    /**
     * oneCheckbox extension - checks that at least one checkbox has been selected in a group
     * Set a checkbox as in a group with data-checkbox-group="GROUPNAME"
     */
    $.validator.addMethod('oneCheckbox', function(value, element, checkboxGroup) {
        return $('input[data-checkbox-group="' + checkboxGroup + '"]:checked').length > 0;
    }, 'You must select at least one option below');

    /**
     * CKEditor doesn't update the textarea until after the form is checked
     * so getData() on the instance and check that
     */
    $.validator.addMethod('ckeRequired', function(value, element) {

        var textboxId = $(element).attr('id');

        if (CKEDITOR.instances[textboxId].getData().length > 0) {
            return true;
        }

        return false;

    }, 'This field is required');

}(jQuery));
