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
(function ($) {
    attachAlaressDatePicker = function () {
        var $dateinput = $(this);
        if ($dateinput.attr('data-datepicker-id') === undefined) {
            $dateinput.alaressDateTimePicker();
        }
    };

    $(function () {
        $('[data-datepicker]').each(attachAlaressDatePicker);
    });
})(jQuery);
