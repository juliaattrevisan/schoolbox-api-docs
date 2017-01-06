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

    var options = {
        databaseFormat: 'Y-m-d H:i:S',
        displayFormat: 'd/m/Y h:i K',
        displayFormatDate: 'd/m/Y',
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

        var originalId = element.attr('id');
        var originalName = element.attr('name');
        var originalValue = element.val();

        // We need to do a bit of shuffling of the DOM. We move the original element over to a hidden form input
        // that will hold the date in a DB friendly format for form submission, and we put a readonly textbox in its place for
        // displaying a friendlier date format to the end users
        var format = options.displayFormat;
        if($('#allday').val() == 1) {
            var format = options.displayFormatDate;
        }
        element.after('<input type="hidden" id="' + originalId + '" name="' + originalName + '" value="' + originalValue + ' ">');
        element.after('<input data-original-id="' + originalId + '" style="cursor:pointer;" type="text" id="pickerDisplay' + window.pickerCount + '" name=pickerDisplay"' + window.pickerCount + '" value="' + moment(originalValue, convertFormat(options.databaseFormat)).format(convertFormat(format)) + '">');
        element.after('<div data-reveal id="picker' + window.pickerCount + '" class="reveal-modal"></div>');
        element.remove();

        element = $('#pickerDisplay' + window.pickerCount);

        // Store the ID of the datetime picker on the element for use in the click event

        element.data('pickerId', window.pickerCount);

        // Create click event to open the modal for the element

        element.click(function () {

            var elementClicked = $(this);
            var pickerId = + elementClicked.data('pickerId');
            var picker = $('#picker' + pickerId);

            // Only AJAX in the picker if we haven't already

            if (picker.data('status') === undefined) {

                // Update picker status

                picker.data('status', 'loading');


                // if datetime, pass date only

                // AJAX in the modal contents if they haven't already been loaded
                picker.load('/picker/datetime/display/' + encodeURIComponent(options.displayFormat) + '/database/' + encodeURIComponent(options.databaseFormat) + '/id/' + pickerId + '/value/' + encodeURI(originalValue), function () {

                    $('#pickerCalendar' + pickerId).data('dateDisplay', elementClicked.val());

                    // Capture closure of modal

                    $(document).on('closed.fndtn.reveal', '#picker' + pickerId, function () {

                        // Update the elements value with the selected date
                        var format = options.displayFormat;
                        if($('#allday').val() == 1) {
                            var format = options.displayFormatDate;
                        }

                        var pickerDisplay = $('#pickerDisplay' + pickerId);
                        pickerDisplay.val(moment(window.pickerInstance[pickerId].altInput.value, convertFormat(options.databaseFormat)).format(convertFormat(format)));

                        var originalField = $('#' + pickerDisplay.data('originalId'));
                        originalField.val(window.pickerInstance[pickerId].altInput.value);
                    });

                    // Update picker status

                    picker.data('status', 'loaded');

                    // Display the datetime picker modal

                    $('#picker' + pickerId).foundation('reveal', 'open');
                });

            } else if (picker.data('status') == 'loaded') {

                // Put the elements current value back in to the modal just in case it had changed somehow

                var pickerDisplay = $('#pickerDisplay' + pickerId);
                var originalField = $('#' + pickerDisplay.data('originalId'));

                window.pickerInstance[pickerId].setDate(originalField.val());

                $('#pickerCalendar' + pickerId).data('dateDisplay', elementClicked.val());

                // Since the contents have already been AJAX'd in, we can just show the modal again

                picker.foundation('reveal', 'open');
            }

        });

        // Increment global counter

        window.pickerCount++;
    });
};
