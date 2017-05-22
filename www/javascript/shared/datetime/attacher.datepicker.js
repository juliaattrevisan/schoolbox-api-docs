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

            var dictChooseDate = 'Select Date';

            var $dateinput = $(this);

            if ($dateinput.attr('data-datepicker-id') === undefined) {

                if ($dateinput.attr('data-datepicker-nobutton') === undefined) {

                    // rearrange the DOM to install the button
                    var $label = $dateinput.closest('label');

                    if ($label.length) {
                        // in this case, the DOM needs to be rearranged so that the flex button is adjacent tot he label, not inside
                        $label.attr('for', $dateinput.attr('id'));
                        $dateinput.detach();
                        $label.after($dateinput);
                    }

                    $dateinput.wrap($('<div class="input-group" />'));

                    var $datebtn = $('<a class="button icon-calendar" data-adtp-btn />');

                    if (dictChooseDate) {
                        $datebtn.text(dictChooseDate);
                    } else {
                        $datebtn.addClass('no-text');
                    }

                    $dateinput.after($datebtn.append($('<span class="splash animate"/>')));
                }

                $dateinput.alaressDateTimePicker();

                // Add jquery validate ignore data attr
                $dateinput.attr('data-av-ignore', true);

                // delegate blurs to text inputs
                $dateinput.on('blur', function (e) {
                    var t = e.target;

                    $(t).prop('readonly', true);
                    $(t).prop('disabled', true);

                    $.get('/ajax/strtotime.php', { string: t.value, format: 'local_full' }, function (data) {
                        $(t).prop('disabled', false);

                        $(t).val(data.time);

                        // Remove validation ignore data attr
                        $(t).removeAttr('data-av-ignore');

                        // re-run form validation
                        $(t).closest('form').valid();

                    }).always(function () {
                        $(t).prop('readonly', false);
                        $(t).prop('disabled', false);
                    })
                });

                $dateinput.on('focus', function() {

                    // Add the ignore data attr again
                    $dateinput.attr('data-av-ignore', true);

                });
            }
        });

    });
})(jQuery);