/**
 * Hour picker, based on timepicker
 *
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
(function($){
    $.fn.hourpicker = function(options) {

        // Default set of options
        var thisOptions = $.extend(
            {},
            {
                timeFormat: "H",
                minTime: '00:00',
                maxTime: '23:59',
                step: 60
            },
            options
        );

        // Options for hours: 12 or 24 hour selector
        switch (thisOptions.hours) {
            case 12:
                $.extend(
                    thisOptions,
                    {
                        timeFormat: "h",
                        minTime: '01:00am',
                        maxTime: '12:59pm',
                    }
                );
                break;
            case 24:
                $.extend(
                    thisOptions,
                    {
                        timeFormat: "H",
                        minTime: '00:00',
                        maxTime: '23:59',
                    }
                );
                break;
        }

        return this.timepicker(thisOptions);
    }
})(jQuery);
