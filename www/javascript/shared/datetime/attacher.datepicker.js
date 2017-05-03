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

            var attr = $(this).attr('data-datepicker-time'),
                options;

            if (typeof attr !== typeof undefined && attr !== false) { // datetime

                options = {
                    mask: "1/2/y h:s t\\m",
                    placeholder: "dd/mm/yyyy hh:mm xm",
                    alias: "datetime",
                    hourFormat: "12"
                };

            } else { // date

                options = {
                    mask: "1/2/y",
                    placeholder: "dd/mm/yyyy",
                    alias: "date",
                    hourFormat: "12"
                };

            }

            $(this).find('input').inputmask(options);

        })
    });
})(jQuery);
git reb