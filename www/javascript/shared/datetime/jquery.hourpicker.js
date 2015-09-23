/**
 * Hour picker, based on timepicker
 *
 * Has built-in support for a 12 hour (1-12) and 24 hour (0-23) picker, with the
 * "hours" option.
 *
 * All other options get passed directly to timepicker
 *
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
(function($){
    $.fn.hourpicker = function(options) {
        var leadingZero = options.leadingZero || false;
        // Default set of options
        var thisOptions = $.extend(
            {},
            {
                timeFormat: leadingZero ? "H" : "G",
                minTime: '00:00',
                maxTime: '23:59',
                step: 60,
                appendTo: function ($input) {
                    return $input.closest('.timepick');
                }
            },
            options
        );

        // Options for hours: 12 or 24 hour selector
        switch (thisOptions.hours) {
            case 12:
                $.extend(
                    thisOptions,
                    {
                        timeFormat: leadingZero ? "h" : "g",
                        minTime: '01:00am',
                        maxTime: '12:59pm'
                    }
                );
                break;
            case 24:
                $.extend(
                    thisOptions,
                    {
                        timeFormat: leadingZero ? "H" : "G",
                        minTime: '00:00',
                        maxTime: '23:59'
                    }
                );
                break;
        }
        delete thisOptions.hours;
        delete thisOptions.leadingZero;

        return this.timepicker(thisOptions);
    }
})(jQuery);
