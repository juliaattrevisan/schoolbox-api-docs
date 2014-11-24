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

        return getDateFromString(date, hour, minute, 0)
    }

    var getDateFromString = function(date, hour, minute, second) {
        var date_parts = date.match(/^(\d+)\/(\d+)\/(\d+)$/);
        if (!date_parts) {
            return false;
        }
        return new Date(
            date_parts[3],
            date_parts[2]-1,
            date_parts[1],
            hour,
            minute,
            second
        );
    }

    /**
     * Validator for a datepicker (format: dd/mm/yyyy)
     */
    $.validator.addMethod('datepickerDate', function(value, element) {
        var date = getDateFromString(value, 0, 0, 0);
        return (date !== false);
    }, 'Please enter a valid date');

    /**
     * Extension of datepickerDate for checking that the date is not earlier
     * than another element's date.
     */
    $.validator.addMethod('datepickerDateNotBefore', function(value, element, other_element_selector) {
        var date       = getDateFromString(value, 0, 0, 0);
        var other_date = getDateFromString(other_element_selector.val(), 0, 0, 0);
        return (date !== false && other_date !== false && date.getTime() >= other_date.getTime());
    }, 'Please enter a valid date');

    /**
     * Date-time validation method - works with a time selector as well, and
     * uses some guesswork to find the datetime fields.
     *
     * It's used on the submission create field.
     */
    $.validator.addMethod('dateValid', function(value, element) {
        var date = getNormalizedDate(element);
        return date && date.getTime && !isNaN(date.getTime());
    }, 'Please enter a valid date');

    /**
     * dateValid extension - checks that this field's date is not earlier than
     * than another one.
     */
    $.validator.addMethod('dateNotBefore', function(value, element, other_date_field) {
        var item = $(element);
        var form = item.closest('form');
        return (getNormalizedDate(element) >= getNormalizedDate(other_date_field, form));
    }, 'Please enter a date that is after');

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
     */
    $.validator.addMethod("isURL", function (value, element) {
        if (this.optional(element)) {
            return true;
        }

        // Create a link out of the value, and get its protocol that way.
        var anchor = $('<a />').attr('href', value);
        switch (anchor.prop('protocol')) {
            case 'http:':
            case 'https:':
            case 'ftp:':
                return true;
        }
        return false;
    }, "Please enter a URL beginning with http://, https://, ftp://, or a relative URL (beginning with /)");

}(jQuery));
