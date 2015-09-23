/**
 * Minute picker, based on timepicker
 *
 * All options get passed directly to timepicker
 *
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
(function($){
    $.fn.minutepicker = function(options) {
        return this.each(function() {
            var minutePicker = $(this);
            var minuteValue  = minutePicker.val();
            minutePicker
                .timepicker(
                    $.extend(
                        {},
                        {
                            timeFormat: "i",
                            step: 5,
                            minTime: '12:00am',
                            maxTime: '12:59am',
                            appendTo: function ($input) {
                                return $input.closest('.timepick');
                            }
                        },
                        options
                    )
                )
                .val(minuteValue);
        });
    }
})(jQuery);
