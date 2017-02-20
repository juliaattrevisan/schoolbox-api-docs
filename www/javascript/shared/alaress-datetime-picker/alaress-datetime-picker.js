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

var pickerFormats = {
    from: '',
    to: ''
}

function setPickerFormats (allDayState, element) {
    // No matter what, we are overriding the format
    //Todo: decide if we need to check buddy elements formats or just use own formats
    //Todo: Rewrite this function, this is just one condition when we need to reformat the date
    var refreshOptions = {
        displayFormat: 'd/m/Y h:iK', //Default display format
        displayFormatDate: 'd/m/Y',
        databaseFormat: 'Y-m-d H:i:s',
        enableTime: getValue(element, 'data-datepicker-time'),
        allDay: getValue(element, 'data-datepicker-allday'), //Show All Day switch
        allDayExt: getValue(element, 'data-datepicker-alldayext'), //Sync with external AllDay input hidden field
        altFormat: getValue(element, 'data-datepicker-format'), // false|date|datetime
    };
    console.log(refreshOptions);
    if (refreshOptions.altFormat !== false ){
        var l = 1;
        if (refreshOptions.altFormat == 'date') {
            l = 11;
            pickerFormats.from = refreshOptions.displayFormatDate;
            pickerFormats.to = refreshOptions.displayFormatDate;
        }
        else if (pickerOptions.altFormat == 'datetime') {
            l = 12;
            pickerFormats.from  = refreshOptions.displayFormat;
            pickerFormats.to = refreshOptions.displayFormat;
        }
    }
    else {
        l = 2;
        // If not overiding then looking at the enableTime option
        if (refreshOptions.enableTime === false) {
            l = 22;
            pickerFormats.from = refreshOptions.displayFormatDate;
            pickerFormats.to = refreshOptions.displayFormatDate;
        }
        else {
            l = 3;
            // It depends again...
            // Looking at the allDay option
            if (refreshOptions.allDay === true) {
                l = 4;
                // Ok, the allDay changed, changing the format accordingly
                if (allDayState) {
                    l = 41;
                    pickerFormats.from = refreshOptions.displayFormat;
                    pickerFormats.to = refreshOptions.displayFormatDate;
                }
                else {
                    l = 42;
                    // pickerFormats.from = refreshOptions.displayFormatDate;
                    pickerFormats.from = refreshOptions.displayFormat;
                    pickerFormats.to = refreshOptions.displayFormat;
                }
            }
            else {
                l = 5;
                pickerFormats.from = refreshOptions.displayFormat;
                pickerFormats.to = refreshOptions.displayFormat;
            }
        }
    }

    console.log('format set: '+l);
}

function manageAllDay ( state, element, options ) {
       console.log(options);
//        console.log("manageAllDay>>> state: " + state + ' > doFormat: ' + doFormat );

    if(options.allDayExt !==false) {
        // Get all linked inputs, e.g. startDate, endDate
        var selector = $('[data-datepicker-alldayext = '+ options.allDayExt +']');
    }
    else {
        var selector = element;
    }

    // Change either the elememt itself of the element and it's AllDay buddy
    selector.each( function() {
        // Get their pickerIds
        var pId = $(this).attr('data-datepicker-id');

        // Get the format
        setPickerFormats(state, $(this));

        if(window.pickerInstance[pId] !== undefined) {
            var value = window.pickerInstance[pId].input.value;
        }
        else {
            var value = $(this).val();
        }
        // All Day is on
        if(state === true) {

            if(options.endDay) {
                var v = moment(value, convertFormat(pickerFormats.from)).endOf('day').format(convertFormat(pickerFormats.to));
            }
            else {
                var v = moment(value, convertFormat(pickerFormats.from)).startOf('day').format(convertFormat(pickerFormats.to));
            }
            if(window.pickerInstance[pId] !== undefined) {
                // window.pickerInstance[pId].input.value = v;
                window.pickerInstance[pId].setDate(moment(v, convertFormat(pickerFormats.to)).format(convertFormat(options.databaseFormat)), true);
            }
            $(this).val(v);

        }
        else {
            var v = moment(value, convertFormat(pickerFormats.from)).format(convertFormat(pickerFormats.to));
            if(window.pickerInstance[pId] !== undefined) {
                // window.pickerInstance[pId].input.value = v;
                window.pickerInstance[pId].setDate(moment(v, convertFormat(pickerFormats.to)).format(convertFormat(options.databaseFormat)), true);
            }
            $(this).val(v);
        }
        if(pickerOptions.allDayExt !== false) {
            $('#'+pickerOptions.allDayExt).val(state?1:0);
        }
        console.log(value+' '+v+' '+state+' '+pickerFormats.from+' '+pickerFormats.to);
    });

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
    var options = {
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

    };

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

    element.click(function () {

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

        // var elementClicked = $(this);
        var picker = $('#picker' + pickerId);

        // Only AJAX in the picker if we haven't already

        if (picker.data('status') === undefined) {

            // Update picker status
            picker.data('status', 'loading');
            // if datetime, pass date only
            // AJAX in the modal contents if they haven't already been loaded
            picker.load('/picker/datetime/type/' + options.type + '/element/' + element.attr('id') + '/id/' + pickerId + '/value/' + encodeURIComponent(originalValue), function () {

                // Capture open of modal
                $(document).on('opened.fndtn.reveal', '#picker' + pickerId, function () {

                    // Check and refresh visibility of time and allDay state
                    // Hide time picker if allday hidden field is set to 1
                    if (pickerOptions.allDayExt !== false && $('#' + pickerOptions.allDayExt).val() == 1) {
                        checkAllday(true, pickerId);
                    }
                    else if (pickerOptions.allDayExt !== false && $('#' + pickerOptions.allDayExt).val() == 0) {
                        checkAllday(false, pickerId);
                    }

                });

                // Capture closure of modal
                $(document).on('closed.fndtn.reveal', '#picker' + pickerId, function () {

                    // nope

                });
                // window.pickerInstance[pickerId].setDate(element.val());
                // Update picker status

                picker.data('status', 'loaded');

                // Display the datetime picker modal

                picker.foundation('reveal', 'open');
                // Initialise tabs
                picker.foundation('reflow', 'tab');



                $('#byDate-'+pickerId).find('.picker-submit').click(function() {
                    if ($('#hideTime'+pickerId).is(':checked')) {
                        manageAllDay (true, element, options )
                    }
                    else {
                        manageAllDay(false, element, options )
                    }
                    // element.val(window.pickerInstance[pickerId].input.value);
                    picker.foundation('reveal', 'close');

                });

            });

        } else if (picker.data('status') == 'loaded') {

            // Use Y-m-d to avoid mis interpreting d/m/Y
            // var formatDate = moment(element.val(), convertFormat(options.displayFormat)).format(convertFormat(options.databaseFormat));
            // window.pickerInstance[pickerId].setDate(formatDate);

            // $('#pickerCalendar' + pickerId).data('dateDisplay', element.val());

            // Since the contents have already been AJAX'd in, we can just show the modal again

            picker.foundation('reveal', 'open');



        }
    });

};
