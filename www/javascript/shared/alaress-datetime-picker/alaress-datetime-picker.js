// To keep the date format string the same between the flatpickr format string, and MomentJS need to create a mapping function

convertFormat = function (format) {
    var formatMap = {
            d: 'DD',
            D: 'ddd',
            j: 'D',
            J: 'Do',
            l: 'dddd',
            N: 'E',
            K: 'A',
            S: 'SS',
            w: 'd',
            W: 'W',
            F: 'MMMM',
            m: 'MM',
            M: 'MMM',
            n: 'M',
            o: 'GGGG',
            Y: 'YYYY',
            y: 'YY',
            a: 'a',
            A: 'A',
            g: 'h',
            G: 'H',
            h: 'hh',
            H: 'HH',
            i: 'mm',
            s: 'ss',
            O: 'ZZ',
            P: 'Z'
        },

        formatEx = /[dDjJlNKSwWFmMnoYyaAgGhHisOP]/g;

    // Just return the converted format
    return format.replace(formatEx, function (format) {
        return formatMap[format];
    });
};

//Use global counter in case multiple alaressDateTimePicker() have been called from one page

var pickerCounter = 0;
countPickers = function () {
    pickerCounter++;
    return pickerCounter;
};

function getValue(input, attribute) {
    var value = input.attr(attribute);
    // There is no such attribute, return false
    if (value === undefined) {
        return false;
    }
    // The attribute is there and has a value, return it
    else if (value.length > 0) {
        return value;
    }
    // The attribute is there, but no value is set, return true
    else {
        return true;
    }
}

function getOptions(element) {

    var buttonText = getValue(element, 'data-datepicker-buttontext');

    // Build datepicker options form the element data attributes
    var elementOptions = {
        noButton: getValue(element, 'data-datepicker-nobutton'),
        buttonText: buttonText === true ? '' : (buttonText || 'Select Date'),
        displayFormat: dateDisplayFormats.localDateTime, //Default display datetime format
        displayFormatDate: dateDisplayFormats.localDate, //Default display date format
        databaseFormat: 'Y-m-d H:i:s',
        enableTime: getValue(element, 'data-datepicker-time'),
        allDaySwitch: getValue(element, 'data-datepicker-alldayswitch'), //Show All Day switch
        allDayInput: getValue(element, 'data-datepicker-alldayinput'), //Sync with external AllDay input hidden field
        altFormat: getValue(element, 'data-datepicker-format'), // false|date|datetime
        type: getValue(element, 'data-datepicker-call'), //Ajax call type: events, duework or nothing
        endDay: getValue(element, 'data-datepicker-endofday'), // Should allday change time to 23:59:59
        endPeriod: getValue(element, 'data-datepicker-endofperiod')// It will use end of the period to set time through timetable picker
    };
    return elementOptions;
}

function getPickerFormats(allDayState, options) {
    // Getting element date/datetime format settings
    var from, to;
    // Format is always date or datetime
    if (options.altFormat !== false) {
        if (options.altFormat === 'date') {
            from = options.displayFormatDate;
            to = options.displayFormatDate;
        }
        else if (options.altFormat === 'datetime') {
            from = options.displayFormat;
            to = options.displayFormat;
        }
    }
    else {
        // If no time, format is date
        if (options.enableTime === false) {
            from = options.displayFormatDate;
            to = options.displayFormatDate;
        }
        else {
            // Or... looking at the allDaySwitch option
            if (options.allDaySwitch === true) {
                // Ok, the allDaySwitch changed, changing the format accordingly
                if (allDayState) {
                    from = options.displayFormat;
                    to = options.displayFormatDate;
                }
                else {
                    from = options.displayFormat;
                    to = options.displayFormat;
                }
            }
            else {
                from = options.displayFormat;
                to = options.displayFormat;
            }
        }
    }
    var formats = {
        convertFrom: from,
        convertTo: to
    };
    return formats;
}

function getLinkedElements(element, options) {
    // Get all linked inputs, e.g. startDate, endDate
    var elements = $('[data-datepicker-alldayinput = ' + options.allDayInput + ']');
    var linkedElements = [];
    elements.each(function () {
        if ($(this)[0] !== element[0]) {
            linkedElements.push($(this));
        }
    });
    return linkedElements;
}

function setValue(element, options, state, trigger, date) {
    // Get their pickerIds
    var pId = element.attr('data-datepicker-id');

    // Get the format
    var pickerFormats = getPickerFormats(state, options);
    var value;

    if (date !== undefined) {
        value = moment(date);
    }
    else if (window.pickerInstance[pId] !== undefined && window.pickerInstance[pId].input.value) {
        value = moment(window.pickerInstance[pId].input.value);
    }
    else if (element.val() !== undefined && element.val()) {
        value = moment(element.val(), convertFormat(pickerFormats.convertFrom));
    }
    else {
        value = moment();
    }
    // All Day is on
    var v;
    if (state === true) {
        if (options.endDay) {
            v = value.endOf('day').format(convertFormat(pickerFormats.convertTo));
        }
        else {
            v = value.startOf('day').format(convertFormat(pickerFormats.convertTo));
        }
        if (window.pickerInstance[pId] !== undefined) {
            window.pickerInstance[pId].setDate(v.toISOString);
        }
        element.val(v);
    }
    else {
        v = moment(value).format(convertFormat(pickerFormats.convertTo));
        if (window.pickerInstance[pId] !== undefined) {
            window.pickerInstance[pId].setDate(v.toISOString);
        }
        element.val(v);
    }
    if (trigger === true) {
        element.trigger('change');
    }
    if (options.allDayInput !== false) {
        $('#' + options.allDayInput).val(state ? 1 : 0);
    }
}

function checkAllDaySwitch (state, pickerId) {

    var $timeDiv = $('#pickerCalendar' + pickerId).closest('#picker' + pickerId).find('.flatpickr-time');

    // All Day is on
    if (state === true) {
        $timeDiv.addClass('hide');
        $('#hideTime' + pickerId).prop('checked', true);

    }
    else {
        $timeDiv.removeClass('hide');
        $('#hideTime' + pickerId).prop('checked', false);
    }

}


/**
 * Massages the datetime picker's value into a valid format, or errors out if it cannot.
 *
 * This uses an AJAX call to /ajax/strtotime.php, which uses method strtotime()
 * to convert the input's value to a recognisable date value.
 *
 * @param e a jQuery event
 */
function massageDateValue(e) {
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
        enableValidation($(t));

    }).always(function () {
        $(t).prop('readonly', false);
        $(t).prop('disabled', false);
    });
}


/**
 * Disables validation on this datepicker.
 *
 * @param element a datepicker
 */
function disableValidation(element) {
    element.attr('data-av-ignore', true);
}


/**
 * Enables validation on this datepicker.
 *
 * @param element a datepicker
 */
function enableValidation(element) {
    element.removeAttr('data-av-ignore');
}


// Extend jQuery with our datepicker function

$.fn.alaressDateTimePicker = function (optionsOverride) {

    var element = this;

    // Temporarily disable JS validation on this field so that it doesn't harass the user that
    // things like 'today' aren't valid dates
    disableValidation(element);

    // Build datepicker options form the element data attributes
    var options = getOptions(element);

    // Override with user any options --- should this stay? Todo
    for (var attribute in optionsOverride) {
        options[attribute] = optionsOverride[attribute];
    }

    if (!options.noButton && !element.data('dateButton')) {
        // install button (no more than once)

        // rearrange the DOM to install the button
        var $label = element.closest('label');

        if ($label.length) {
            // in this case, the DOM needs to be rearranged so that the flex button is adjacent tot he label, not inside
            $label.attr('for', element.attr('id'));
            element.detach();
            $label.after(element);
        }

        element.wrap($('<div class="input-group" />'));

        var $datebtn = $('<a class="button icon-calendar" data-adtp-btn />');

        if (options.buttonText) {
            $datebtn.text(options.buttonText);
        } else {
            $datebtn.addClass('no-text');
        }

        element
            .data('dateButton', $datebtn)
            .after($datebtn.append($('<span class="splash animate"/>')));
    }

    var $datepickerButton = $(this).siblings('[data-adtp-btn]');

    window.pickerInstance = [];

    // Count this picker in
    var pickerId = countPickers();

    // Set pickerID to the element attr
    element.attr('data-datepicker-id', pickerId);

    var modalSize = (options.type == 'event' || options.type == 'duework') ? 'pickerDouble' : 'pickerSingle';
    $('body').append('<div data-reveal id="picker' + pickerId + '" class="adtp reveal-modal no-pad ' + modalSize + '" data-adtp></div>');

    // Create click event to open the modal for the element

    element.change(function () {
        if (window.pickerInstance[pickerId] !== undefined) {
            window.pickerInstance[pickerId].setDate(moment(element.val(), convertFormat(options.displayFormat)).toISOString());
        }
    });

    element
        .on('blur', massageDateValue)
        .on('focus', function() {
            // Disable JS validation on the field again
            disableValidation($(this));
        });

    $datepickerButton.click(function () {

        var originalValue;

        if (element.val().length > 0) {
            originalValue = moment( element.val(), convertFormat(options.displayFormat));
        }
        // if no value has been passed, use today
        else {
            originalValue = moment();
        }
        if (options.allDayInput !== false && $('#' + options.allDayInput).val() == 1) {
            originalValue = moment(originalValue, convertFormat(options.displayFormatDate));
        }

        var picker = $('#picker' + pickerId);

        // Only AJAX in the picker if we haven't already

        if (picker.data('status') === undefined) {

            // Update picker status
            picker.data('status', 'loading');

            //Check if the form is inside another modal
            var reveal = element.parents('*[data-reveal]');
            if (reveal.length > 0) {
                picker.data('reveal', reveal.attr('id'));
            }

            // if datetime, pass date only
            // AJAX in the modal contents if they haven't already been loaded
            picker.load('/picker/datetime/type/' + options.type + '/element/' + element.attr('id') + '/id/' + pickerId + '/value/' + encodeURIComponent(originalValue.format('YYYY-MM-DD HH:mm:ss')), function () {

                // Capture open of modal
                $(document).on('opened.fndtn.reveal', '#picker' + pickerId, function () {

                    // Check and refresh visibility of time and allDaySwitch state
                    // Hide time picker if allday hidden field is set to 1
                    if (options.allDayInput !== false && $('#' + options.allDayInput).val() == 1) {
                        checkAllDaySwitch(true, pickerId);
                    }
                    else if (options.allDayInput !== false && $('#' + options.allDayInput).val() == 0) {
                        checkAllDaySwitch(false, pickerId);
                    }

                });

                // Capture closure of modal
                $(document).on('closed.fndtn.reveal', '#picker' + pickerId, function () {
                    // Open back the parent modal, if any
                    if ((picker.data('reveal').length > 0)) {
                        $('#' + picker.data('reveal')).foundation('reveal', 'open');
                    }
                });
                // Update picker status
                picker.data('status', 'loaded');

                // Display the datetime picker modal
                picker.foundation('reveal', 'open');
                // Initialise tabs
                picker.foundation('reflow', 'tab');

                var $datePicker = $('#byDate-' + pickerId),
                    $timetablePicker = $('#byTimetable-' + pickerId);

                $datePicker.find('.picker-submit').click(function () {
                    if ($('#hideTime' + pickerId).is(':checked')) {
                        setValue(element, options, true, true);
                    }
                    else {
                        setValue(element, options, false, true);
                    }

                    if (options.allDayInput !== false) {
                        var linkedElements = getLinkedElements(element, options);
                        linkedElements.forEach(function (item) {
                            setValue(item, getOptions(item), $('#hideTime' + pickerId).is(':checked'), true);
                        });
                    }

                    // Close modal
                    picker.foundation('reveal', 'close');

                });

                $timetablePicker.find('[data-adtp-tt-submit]').click(function () {

                    var newDate = moment($(this).attr('data-timetable-value'));

                    if (options.enableTime === true) {

                        var selectedPeriod = $timetablePicker.find('[data-tt-period] option:selected').val();

                        if (selectedPeriod == 'allday') {

                            if (options.endDay) {
                                newDate = newDate.endOf('day');
                            } else {
                                newDate = newDate.startOf('day');
                            }

                        } else {

                            var periodTime = $timetablePicker.find('[name*="periodTime' + pickerId + '"]:checked').val();
                            var newTime = moment($timetablePicker.find('[data-tt-period] option:selected').attr('data-' + periodTime + '-time'), 'h:mma');

                            newDate = newDate.hours(newTime.hours()).minutes(newTime.minutes()).seconds(0);
                        }
                    }

                    setValue(element, options, false, true, newDate);

                    if (options.allDayInput !== false) {
                        var linkedElements = getLinkedElements(element, options);
                        linkedElements.forEach(function (item) {
                            setValue(item, getOptions(item), false, true);
                        });
                    }
                    picker.foundation('reveal', 'close');
                });

            });

        } else if (picker.data('status') === 'loaded') {

            // Update picker values, in case element field value has changed
            window.pickerInstance[pickerId].setDate(moment(element.val(), convertFormat(options.displayFormat)).toISOString());

            picker.foundation('reveal', 'open');

        }
    });

};
