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

            var $dateinput = $(this);

            if ($dateinput.attr('data-datepicker-id') === undefined) {

                $dateinput.alaressDateTimePicker();

                // Temporarily disable JS validation on this field so that it doesn't harass the user that
                // things like 'today' aren't valid dates
                $dateinput.attr('data-av-ignore', true);

                $dateinput.on('focus', function() {

                    // Disable JS validation on the field again
                    $dateinput.attr('data-av-ignore', true);

                });
            }
        });

    });
})(jQuery);
