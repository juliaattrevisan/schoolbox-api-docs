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
    var datepickerOnChange = function($datepicker, string, ajaxValidate) {

        if(ajaxValidate) {
            // Block potential form submission until validation returns
            $.ajax({
                async: false,
                dataType: "json",
                url: '/ajax/strtotime.php',
                data: {string: string},
                success: function (data) {
                    $datepicker.val(data.time).blur();
                }
            });
        }
        $datepicker.data('previousValue', string).valid();
    };

    $(function(){
        // $('[data-datepicker]').datepicker({
        //     constrainInput: false,
        //     nextText: '&raquo;',
        //     prevText: '&laquo;',
        //     onSelect: function (string) {
        //         var previousValue = $(this).data('previousValue') || '';
        //         if(string != previousValue){
        //             $(this).trigger('change',{ajaxValidate: false});
        //         }
        //     }
        // }).on('change', function (e, data) {
        //     // Only skip ajax Validation if explicitly set to false
        //     var ajaxValidate = (undefined == data || undefined == data.ajaxValidate || data.ajaxValidate);
        //     datepickerOnChange($(this), this.value, ajaxValidate);
        // });
        function getValue(input, attribute){
            var value = input.attr(attribute);
            // There is no such attribute, return false
            if(value === undefined){
                return false;
            }
            // The attribute is there and has a value, return it
            else if(value.length > 0 ) {
                return value;
            }
            // The attribute is there, but no value is set, return true
            else {
                return true;
            }
        };
        $('[data-datepicker]').each(function() {
            var options = {
                enableTime: getValue($(this), 'data-datepicker-time'),
                // bothFormats: false, //Can use both displayFormat and displayFormatDate
                allDay: getValue($(this), 'data-datepicker-allday'), //Show All Day switch
                // allDayExt: false, //Sync with external AllDay input hidden field
                // allDateTime: 'start', //Set time to start or end of the day: start, finish
                type: getValue($(this), 'data-datepicker-call') //Ajax call type: events, duework or nothing
            }
            $(this).alaressDateTimePicker(options);
        })

        // $('[data-datepicker]').alaressDateTimePicker();
        // $('[data-datepicker-event]').alaressDateTimePicker({type:'event', enableTime: true});
        // $('[data-datepicker-duework]').alaressDateTimePicker({type:'duework', enableTime:true});
    });
})(jQuery);
