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
     * @param ajaxValidate
     */
    // var datepickerOnChange = function($datepicker, string, ajaxValidate) {
    //
    //     if(ajaxValidate) {
    //         // Block potential form submission until validation returns
    //         $.ajax({
    //             async: false,
    //             dataType: "json",
    //             url: '/ajax/strtotime.php',
    //             data: {string: string},
    //             success: function (data) {
    //                 $datepicker.val(data.time).blur();
    //             }
    //         });
    //     }
    //     $datepicker.data('previousValue', string).valid();
    // };

    $(function(){
        $('[data-datepicker]').each(function() {
            $(this).alaressDateTimePicker();
        })
    });
})(jQuery);
