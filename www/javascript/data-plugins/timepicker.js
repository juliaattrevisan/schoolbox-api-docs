/**
 * Generic timepicker setup, based on data attributes
 */
(function($){
    $(function(){
        $('input[data-timepicker="hour"]').hourpicker({ hours: 12 });
        $('input[data-timepicker="minute"]').minutepicker();
    })
})(jQuery);
