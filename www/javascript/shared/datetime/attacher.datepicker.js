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

            if($(this).attr('data-datepicker-id') === undefined) {

                if ($(this).attr('data-datepicker-nobutton') === undefined) {
                    $(this).after('<button type="button" class="button icon-calendar datepicker-button">Select Date</button>');
                    $(this).addClass('datepicker-input');
                }

                $(this).alaressDateTimePicker();

                // delegate blurs to text inputs
                $(this).on('blur', function (e) {
                    var t = e.target;

                    $(t).prop('readonly', true);
                    $(t).prop('disabled', true);

                    $.get('/ajax/strtotime.php', { string: t.value, format: 'local_full' }, function (data) {
                        $(t).prop('disabled', false);

                        $(t).val(data.time);

                        // re-run form validation
                        $(t).closest('form').valid();

                    }).always(function () {
                        $(t).prop('readonly', false);
                        $(t).prop('disabled', false);
                    })
                });
            }
        });

    });
})(jQuery);
git reb