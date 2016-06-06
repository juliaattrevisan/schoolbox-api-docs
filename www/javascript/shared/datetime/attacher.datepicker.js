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
        $('[data-datepicker]').datepicker({
            constrainInput: false,
            nextText: '&raquo;',
            prevText: '&laquo;',
            onSelect: function (string) {
                var input         = $(this);
                var previousValue = input.data('previousValue') || "";

                $.getJSON('/ajax/strtotime.php', {string:string}, function (data) {
                    input.val(data.time).blur();
                });

                // jQuery datepicker no longer triggers a change event.
                // Do it manually.
                if (string != previousValue) {
                    $(this).trigger('change');
                }
                input.data('previousValue', string);

            }
        });
    })
})(jQuery);
