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
     * Handle datepicker change events.
     *
     * @param $datepicker
     * @param string
     * @param async
     */
    var datepickerOnChange = function($datepicker, string, async) {

        var previousValue = $datepicker.data('previousValue') || "";

        // Optionally block potential form submission until validation returns
        $.ajax({
            async: async,
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
                    datepickerOnChange($(this), this.value, true);
                }
            }
        }).on('change', function () {
            datepickerOnChange($(this), this.value, false);
        });
    });
})(jQuery);
