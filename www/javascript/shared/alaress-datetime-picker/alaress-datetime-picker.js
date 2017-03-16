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
            P: 'Z',
        },

        formatEx = /[dDjJlNKSwWFmMnoYyaAgGhHisOP]/g;
        var that = this;
        // Just return the converted format
        return format.replace(formatEx, function (format) {
            return formatMap[format];
        });
};

//Use global counter in case multiple alaressDateTimePicker() have been called from one page

var pickerCounter = 0;
countPickers = function () {
    pickerCounter ++;
    return pickerCounter;
};

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

function getOptions (element) {

    // Build datepicker options form the element data attributes
    var elementOptions = {
        displayFormat: 'd/m/Y h:iK', //Default display format
        displayFormatDate: 'd/m/Y',
        databaseFormat: 'Y-m-d H:i:s',
        enableTime: getValue(element, 'data-datepicker-time'),
        // bothFormats: false, //Can use both displayFormat and displayFormatDate
        allDay: getValue(element, 'data-datepicker-allday'), //Show All Day switch
        allDayExt: getValue(element, 'data-datepicker-alldayext'), //Sync with external AllDay input hidden field
        altFormat: getValue(element, 'data-datepicker-format'), // false|date|datetime
        // allDateTime: 'start', //Set time to start or end of the day: start, finish
        type: getValue(element, 'data-datepicker-call'), //Ajax call type: events, duework or nothing
        endDay: getValue(element, 'data-datepicker-endofday'), // Should allday change time to 23:59:59
        endPeriod: getValue(element, 'data-datepicker-endofperiod'),// It will use end of the period to set time through timetable picker

    };
    return elementOptions;
}

function getPickerFormats (allDayState, options) {
    // Getting element date/datetime format settings
    var from, to;
    // Format is always date or datetime
    if (options.altFormat !== false ){
        if (options.altFormat == 'date') {
            from = options.displayFormatDate;
            to = options.displayFormatDate;
        }
        else if (options.altFormat == 'datetime') {
            from  = options.displayFormat;
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
            // Or... looking at the allDay option
            if (options.allDay === true) {
                // Ok, the allDay changed, changing the format accordingly
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

function getLinkedElements (element, options) {
    // Get all linked inputs, e.g. startDate, endDate
    var elements = $('[data-datepicker-alldayext = '+ options.allDayExt +']');
    var linkedElements = [];
    elements.each( function() {
       if($(this)[0] !== element[0]) {
           linkedElements.push($(this));
       }
    });
    return linkedElements;
}

function setValue ( element, options, state, trigger, date ) {

    // Get their pickerIds
    var pId = element.attr('data-datepicker-id');

    // Get the format
    var pickerFormats = getPickerFormats(state, options);

    if(date !== undefined) {
        var value = date;
    }
    else if(window.pickerInstance[pId] !== undefined && window.pickerInstance[pId].input.value) {
        var value = window.pickerInstance[pId].input.value;
    }
    else if (element.val() !== undefined && element.val()) {
        var value = element.val();
    }
    else {
        var value = moment();
    }
    // All Day is on
    if(state === true) {
        if(options.endDay) {
            var v = moment(value, convertFormat(pickerFormats.convertFrom)).endOf('day').format(convertFormat(pickerFormats.convertTo));
        }
        else {
            var v = moment(value, convertFormat(pickerFormats.convertFrom)).startOf('day').format(convertFormat(pickerFormats.convertTo));
        }
        if(window.pickerInstance[pId] !== undefined) {
            window.pickerInstance[pId].setDate(moment(v, convertFormat(pickerFormats.convertTo)).format(convertFormat(options.databaseFormat)), true, options.databaseFormat);
        }
        element.val(v);
    }
    else {
        var v = moment(value, convertFormat(pickerFormats.convertFrom)).format(convertFormat(pickerFormats.convertTo));
        if(window.pickerInstance[pId] !== undefined) {
            window.pickerInstance[pId].setDate(moment(v, convertFormat(pickerFormats.convertTo)).format(convertFormat(options.databaseFormat)), true, options.databaseFormat);
        }
        element.val(v);
    }
    if(trigger === true) {
        element.trigger('change');
    }
    if(options.allDayExt !== false) {
        $('#'+options.allDayExt).val(state?1:0);
    }

}

function checkAllday( state, pickerId ) {

    var timeDiv = $('#pickerCalendar'+pickerId).closest('#picker'+pickerId).find('.flatpickr-time');

    // All Day is on
    if(state === true) {
        timeDiv.css('display', 'none');
        $('#hideTime'+pickerId).prop('checked', true);

    }
    else {
        timeDiv.css('display', 'block');
        $('#hideTime'+pickerId).prop('checked', false);
    }

};

// Extend jQuery with our datepicker function

$.fn.alaressDateTimePicker = function(optionsOverride) {

    var element = this;

    // Build datepicker options form the element data attributes
    var options = getOptions(element);

    // Override with user any options --- should this stay? Todo
    for (var attribute in optionsOverride) {
        options[attribute] = optionsOverride[attribute];
    }

    window.pickerInstance = [];

    // Count this picker in
    var pickerId = countPickers();

    // Set pickerID to the element attr
    element.attr('data-datepicker-id', pickerId);

    var modalSize = (options.type == 'event' || options.type == 'duework') ? '650px' : '325px';
    element.after('<div data-reveal id="picker' + pickerId + '" class="reveal-modal no-pad" style="width: ' + modalSize + ';"></div>');

    // Create click event to open the modal for the element

    element.change(function() {
        if(window.pickerInstance[pickerId] !== undefined) {
            window.pickerInstance[pickerId].setDate(moment(element.val(), convertFormat(options.displayFormat)).format(convertFormat(options.databaseFormat)), true, options.databaseFormat);
        }
    });

    element.click(function() {

        if (element.val().length > 0) {
            var originalValue = element.val();
        }
        // if no value has been passed, use today
        else {
            var originalValue = moment().format(convertFormat(options.displayFormat));
        }
        if (options.allDayExt !== false && $('#'+options.allDayExt).val() == 1) {
            originalValue = moment(originalValue, convertFormat(options.displayFormatDate)).format(convertFormat(options.displayFormat));
        }

        var picker = $('#picker' + pickerId);

        // Only AJAX in the picker if we haven't already

        if (picker.data('status') === undefined) {

            // Update picker status
            picker.data('status', 'loading');

            //Check if the form is inside another modal
            var reveal = element.parents('*[data-reveal]');
            if (reveal.length > 0){
                picker.data('reveal', reveal.attr('id'));
            }

            // if datetime, pass date only
            // AJAX in the modal contents if they haven't already been loaded
            picker.load('/picker/datetime/type/' + options.type + '/element/' + element.attr('id') + '/id/' + pickerId + '/value/' + encodeURIComponent(originalValue), function () {

                // Capture open of modal
                $(document).on('opened.fndtn.reveal', '#picker' + pickerId, function () {

                    // Check and refresh visibility of time and allDay state
                    // Hide time picker if allday hidden field is set to 1
                    if (options.allDayExt !== false && $('#' + options.allDayExt).val() == 1) {
                        checkAllday(true, pickerId);
                    }
                    else if (options.allDayExt !== false && $('#' + options.allDayExt).val() == 0) {
                        checkAllday(false, pickerId);
                    }

                });

                // Capture closure of modal
                $(document).on('closed.fndtn.reveal', '#picker' + pickerId, function () {
                    // Open back the parent modal, if any
                    if ((picker.data('reveal').length > 0)) {
                        $('#'+picker.data('reveal')).foundation('reveal', 'open');
                    }
                });
                // Update picker status
                picker.data('status', 'loaded');

                // Display the datetime picker modal
                picker.foundation('reveal', 'open');
                // Initialise tabs
                picker.foundation('reflow', 'tab');

                $('#byDate-'+pickerId).find('.picker-submit').click(function() {
                    if ($('#hideTime'+pickerId).is(':checked')) {
                        setValue( element, options, true, true );
                    }
                    else {
                        setValue( element, options, false, true );
                    }

                    if (options.allDayExt !== false) {
                        var linkedElements = getLinkedElements( element, options );
                        linkedElements.forEach(function(item) {
                            setValue(item, getOptions(item), $('#hideTime'+pickerId).is(':checked'), false);
                        });
                    }

                    picker.foundation('reveal', 'close');

                });

                $('#byTimetable-'+pickerId).find('.timetable-submit').click(function() {
                    var periodSelected = $('table').find('[data-selected=1]').attr('data-period');
                    if(options.endPeriod) {
                        var newTime = moment($('[data-period-number=' + periodSelected + ']').attr('data-period-end'), 'h:mma');
                    }
                    else {
                        var newTime = moment($('[data-period-number=' + periodSelected + ']').attr('data-period-start'), 'h:mma');
                    }
                    var newDate = moment($(this).attr('data-timetable-value'), convertFormat(options.displayFormatDate)).hours(newTime.hours()).minutes(newTime.minutes()).seconds(0);
                    setValue( element, options, false, true, newDate);

                    if (options.allDayExt !== false) {
                        var linkedElements = getLinkedElements( element, options );
                        linkedElements.forEach(function(item) {
                            setValue(item, getOptions(item), false, false);
                        });
                    }
                    picker.foundation('reveal', 'close');
                });

            });

        } else if (picker.data('status') == 'loaded') {

            // Update picker values, in case element field value has changed
            window.pickerInstance[pickerId].setDate(moment(element.val(), convertFormat(options.displayFormat)).format(convertFormat(options.databaseFormat)), true, options.databaseFormat);

            picker.foundation('reveal', 'open');

        }
    });

};
