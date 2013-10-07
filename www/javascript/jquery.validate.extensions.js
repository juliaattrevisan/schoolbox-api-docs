/**
 * Custom extensions for jQuery validate plugin.
 *
 * current extensions:
 * * dateValid:     the datepicker value is a valid date
 * * dateNotBefore: the datepicker value is valid, and not before the value of
 *                  another
 */
(function($) {
    var getNormalizedDate = function(date_field, form) {
        var field_name = date_field;
        var element    = null;
        if (typeof date_field === 'object') {
            field_name = $j(date_field).attr('name').replace(/_\w+$/, '');
            element    = $j(date_field).closest('form');
        }
        else {
            element = $j(form);
        }
        var date     = element.find(':input[name="'+field_name+'_date"]').val();
        var hour     = element.find(':input[name="'+field_name+'_hour"]').val();
        var minute   = element.find(':input[name="'+field_name+'_minute"]').val();
        var meridian = element.find(':input[name="'+field_name+'_period"]').val();

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
            if (field_name === 'open') {
                hour   = 0;
                minute = 0;
            }
            else {
                hour   = 23;
                minute = 59;
            }
        }

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
            0
        );
    }

    $.validator.addMethod('dateValid', function(value, element) {
        var date = getNormalizedDate(element);
        return date && date.getTime && !isNaN(date.getTime());
    }, 'Please enter a valid date');

    $.validator.addMethod('dateNotBefore', function(value, element, other_date_field) {
        var item = $j(element);
        var form = item.closest('form');
        return (getNormalizedDate(element) >= getNormalizedDate(other_date_field, form));
    }, 'Please enter a date that is after');

}(jQuery));
