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

                // Temporarily disable JS validation on this field so that it doesn't harass the user that
                // things like 'today' aren't valid dates
                $dateinput.attr('data-av-ignore', true);

                // delegate blurs to text inputs
                $dateinput.on('blur', function (e) {
                    var t = e.target;

                    $(t).prop('readonly', true);
                    $(t).prop('disabled', true);

                    $.get('/ajax/strtotime.php', { string: t.value, format: 'local_full' }, function (data) {

                        var $fieldError = $('#' + $(t).attr('id') + '-error'); // A possible validation error for this field

                        $(t).prop('disabled', false);

                        // If it couldn't parse a date - show a validation error on the form
                        if (data.error) {

                            $(t).addClass('error');

                            if (!$fieldError.length) { // don't create multiple errors

                                var error = '<label id="' + $(t).attr('id') + '-error" class="error" for="' + $(t).attr('id') + '">Please enter a valid date</label>';

                                if ($(t).closest('.input-group').length) {
                                    $(t).closest('.input-group').after(error);
                                } else {
                                    $(t).after(error);
                                }

                            } else {

                                // It might be hidden - show
                                $fieldError.show();
                            }

                        } else {

                            // Add the parsed date into the field
                            $(t).val(data.time);

                            // Remove any error class on the field
                            $(t).removeClass('error');

                            // Hide any validation error
                            $fieldError.hide();
                        }

                        // Restore JS validation on the field
                        $(t).removeAttr('data-av-ignore');

                    }).always(function () {
                        $(t).prop('readonly', false);
                        $(t).prop('disabled', false);
                    })
                });

                $dateinput.on('focus', function() {

                    // Disable JS validation on the field again
                    $dateinput.attr('data-av-ignore', true);

                });
            }
        });

    });
})(jQuery);