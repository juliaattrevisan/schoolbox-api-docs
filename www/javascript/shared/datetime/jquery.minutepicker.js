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
                .val(minuteValue)
                .on('keyup', function() {
                    // Store and restore to work around time set to 0 when
                    // selecting a number < 10 or so.
                    minutePicker.data('actualValue', minutePicker.val());
                })
                .on('change', function() {
                    var actualValue = minutePicker.data('actualValue');
                    minutePicker.removeData('actualValue');
                    if (typeof actualValue != 'undefined') {
                        // Set both here and setTimeout as I don't know
                        // the order which events will be fired.
                        minutePicker.val(actualValue);
                        setTimeout(function() { minutePicker.val(actualValue); }, 0);
                    }
                });
        });
    }
})(jQuery);
