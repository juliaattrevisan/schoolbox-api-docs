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
     * @param datepicker
     * @param string
     * @param allowChangeTrigger
     */
    var datepickerOnChange = function(datepicker, string, allowChangeTrigger) {
        var previousValue = datepicker.data('previousValue') || "";

        $.getJSON('/ajax/strtotime.php', {string:string}, function (data) {
            datepicker.val(data.time).blur();
        });

        // jQuery datepicker no longer triggers a change event.
        // Do it manually.
        if (allowChangeTrigger && string != previousValue) {
            datepicker.trigger('change');
        }
        datepicker.data('previousValue', string);
    };

    $(function(){
        $('[data-datepicker]').datepicker({
            constrainInput: false,
            nextText: '&raquo;',
            prevText: '&laquo;',
            onSelect: function (string) {
                datepickerOnChange($(this), string, true);
            }
        }).on('change', function () {
            datepickerOnChange($(this), this.value, false);
        });
    });
})(jQuery);
