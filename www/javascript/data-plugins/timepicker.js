/**
 * Generic timepicker setup, based on data attributes
 *
 * Usage:
 * <input type="text" data-timepicker="hour" />
 * <input type="text" data-timepicker="minute" />
 *
 * Requires:
 * * timepicker
 * * hourpicker (custom extension to timepicker)
 * * minutepicker (custom extension to timepicker)
 */
(function($){
    $(function(){
        $('input[data-timepicker="hour"]').hourpicker({ hours: 12 });
        $('input[data-timepicker="minute"]').minutepicker();
    })
})(jQuery);
