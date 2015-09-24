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
            onClose:function (string) {
                var input = $(this);
                $.getJSON('/ajax/strtotime.php', {string:string}, function (data) {
                    input.val(data.time).blur();
                })
            }
        });
    })
})(jQuery);
