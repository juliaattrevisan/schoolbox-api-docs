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

    /**
     * Perform a blocking (synchronous) validation
     *
     * @param $datepicker
     * @param string
     */
    var syncValidate = function($datepicker, string) {

        var previousValue = $datepicker.data('previousValue') || "";

        // Block potential form submission until validation returns
        $.ajax({
            async: false,
            dataType: "json",
            url: '/ajax/strtotime.php',
            data: {string:string},
            success: function (data) {
                $datepicker.val(data.time).blur();
            }
        });

        $datepicker.data('previousValue', string);
    };

    $(function(){
        $('[data-datepicker]').datepicker({
            constrainInput: false,
            nextText: '&raquo;',
            prevText: '&laquo;',
            onSelect: function (string) {
                if(string != $(this).data('previousValue')){
                    $(this).trigger('change',{ajaxValidate: false});
                }
            }
        }).on('change', function (e, data) {
            // Only skip ajax Validation if explicitly set to false
            if (undefined == data || undefined == data.ajaxValidate || data.ajaxValidate) {
                syncValidate($(this), this.value);
            }
        });
    });
})(jQuery);
