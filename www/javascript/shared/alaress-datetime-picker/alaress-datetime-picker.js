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

// Extend jQuery with our datepicker function

$.fn.alaressDateTimePicker = function(optionsOverride) {

    // Default options
    // Default picker type: event
    var options = {
        displayFormat: 'd/m/Y h:iK',
        displayFormatDate: 'd/m/Y',
        databaseFormat: 'Y-m-d H:i:s',
        type: 'event'
    };

    // Override with user any options
    for (var attribute in optionsOverride) {
        options[attribute] = optionsOverride[attribute];
    }
    // Counter for uniquely identifying these things

    window.pickerCount = 0;

    // Keep track of picker instances that we've created

    window.pickerInstance = [];

    // Iterate all the elements that want datetime pickers

    var elements = this;

    elements.each(function (index, element) {

        var element = $(element);

        var originalValue = element.val();
        if($('#allday').val() == 1) {
            originalValue = moment(originalValue, convertFormat(options.displayFormatDate)).format(convertFormat(options.displayFormat))
        }

        element.attr('data-pickerId', window.pickerCount);

        element.after('<div data-reveal id="picker' + window.pickerCount + '" class="reveal-modal"></div>');

        // Create click event to open the modal for the element

        element.click(function () {
            var elementClicked = $(this);
            var pickerId = + elementClicked.attr('data-pickerId');
            var picker = $('#picker' + pickerId);

            // Only AJAX in the picker if we haven't already

            if (picker.data('status') === undefined) {

                // Update picker status
                picker.data('status', 'loading');

                // if datetime, pass date only
                // AJAX in the modal contents if they haven't already been loaded
                picker.load('/picker/datetime/type/'+options.type+'/id/' + pickerId + '/value/' + encodeURIComponent(originalValue), function () {

                    // $('#pickerCalendar' + pickerId).data('dateDisplay', elementClicked.val());

                    // Capture closure of modal

                    $(document).on('closed.fndtn.reveal', '#picker' + pickerId, function () {

                    // Update the elements value with the selected date
                        if($('#allday').val() == 1) {
                            element.val(moment(window.pickerInstance[pickerId].input.value, convertFormat(options.displayFormat)).format(convertFormat(options.displayFormatDate)));
                        }
                        else {
                            element.val(window.pickerInstance[pickerId].input.value, convertFormat(options.displayFormat));
                        }

                    });

                    // Update picker status

                    picker.data('status', 'loaded');

                    // Display the datetime picker modal

                    $('#picker' + pickerId).foundation('reveal', 'open');
                });

            } else if (picker.data('status') == 'loaded') {

                // Use Y-m-d to avoid mis interpreting d/m/Y
                var formatDate = moment(element.val(), convertFormat(options.displayFormat)).format(convertFormat(options.databaseFormat));
                window.pickerInstance[pickerId].setDate(formatDate);

                $('#pickerCalendar' + pickerId).data('dateDisplay', elementClicked.val());

                // Since the contents have already been AJAX'd in, we can just show the modal again

                picker.foundation('reveal', 'open');
            }

        });

        // Increment global counter

        window.pickerCount++;
    });
};
