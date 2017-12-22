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
        $('[data-datepicker]').each(attachAlaressDatePicker);
    });
})(jQuery);


function attachAlaressDatePicker() {
    var $dateinput = $(this);

    if ($dateinput.attr('data-datepicker-id') === undefined) {

        var staller = null;

        if ($dateinput.closest('[data-mage]').length) {
            // find the submit button and use that to stall (and init if not already)
            staller = $dateinput.closest('[data-mage]').find('[data-mage-next]').stallSubmit({ event:'click' });

        } else if ($dateinput.closest('#resource-booking-form').length || $dateinput.closest('#participant-customise-modal').length) {
            // Temporarily detach the staller form the resource booking form.
            staller = null;

        } else if ($dateinput.closest('form').length) {
            // use the form itself as the stall target
            staller = $dateinput.closest('form');

        }

        $dateinput.alaressDateTimePicker({
            staller: staller
        });
    }
}