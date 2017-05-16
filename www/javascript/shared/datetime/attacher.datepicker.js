/**
 * Automatically apply a plugin to a predefined data attribute.
 * This saves you from having to instantiate plugins manually.
 *
 * Generic datepicker setup, based on data attributes
 *
 * Usage:
 * <input type="text" data-datepicker />
 *
 * Requires:
 * * datepicker
 */
(function($){

    $(function(){
        $('[data-datepicker]').each(function() {

            if($(this).attr('data-datepicker-id') === undefined) {
                $(this).alaressDateTimePicker();
            }
        });

    });
})(jQuery);
git reb