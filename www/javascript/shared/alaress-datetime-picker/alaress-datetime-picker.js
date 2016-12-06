// To keep the date format string the same between the flatpickr format string, and MomentJS need to create a mapping function

(function (m) {
    var formatMap = {
            d: 'DD',
            D: 'ddd',
            j: 'D',
            J: 'Do',
            l: 'dddd',
            N: 'E',
            K: 'A',
            S: function () {
                return '[' + this.format('Do').replace(/\d*/g, '') + ']';
            },
            w: 'd',
            z: function () {
                return this.format('DDD') - 1;
            },
            W: 'W',
            F: 'MMMM',
            m: 'MM',
            M: 'MMM',
            n: 'M',
            t: function () {
                return this.daysInMonth();
            },
            L: function () {
                return this.isLeapYear() ? 1 : 0;
            },
            o: 'GGGG',
            Y: 'YYYY',
            y: 'YY',
            a: 'a',
            A: 'A',
            B: function () {
                var thisUTC = this.clone().utc(),
                    // Shamelessly stolen from http://javascript.about.com/library/blswatch.htm
                    swatch = ((thisUTC.hours() + 1) % 24) + (thisUTC.minutes() / 60) + (thisUTC.seconds() / 3600);
                return Math.floor(swatch * 1000 / 24);
            },
            g: 'h',
            G: 'H',
            h: 'hh',
            H: 'HH',
            i: 'mm',
            s: 'ss',
            u: '[u]', // not sure if moment has this
            e: '[e]', // moment does not have this
            I: function () {
                return this.isDST() ? 1 : 0;
            },
            O: 'ZZ',
            P: 'Z',
            T: '[T]', // deprecated in moment
            Z: function () {
                return parseInt(this.format('ZZ'), 10) * 36;
            },
            c: 'YYYY-MM-DD[T]HH:mm:ssZ',
            r: 'ddd, DD MMM YYYY HH:mm:ss ZZ',
            U: 'X'
        },
        formatEx = /[dDjlNSwzWFmMntLoYyaABgGhHisueIOPTZcrUJK]/g;

    moment.fn.formatFlatpickr = function (format) {
        var that = this;

        return this.format(format.replace(formatEx, function (phpStr) {
            return typeof formatMap[phpStr] === 'function' ? formatMap[phpStr].call(that) : formatMap[phpStr];
        }));
    };
}(moment));

// Extend jQuery with our datepicker function

$.fn.alaressDateTimePicker = function(optionsOverride) {

    // Default options

    var options = {
        databaseFormat: 'Y-m-d H:i:S',
        displayFormat: 'l, F J Y h:iK'
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

        element.after('<input type="hidden" id="' + originalId + '" name="' + originalName + '" value="' + originalValue + '">');
        element.after('<input readonly data-original-id="' + originalId + '" style="cursor:pointer;" type="text" id="pickerDisplay' + window.pickerCount + '" name=pickerDisplay"' + window.pickerCount + '" value="' + moment(originalValue).formatFlatpickr(options.displayFormat) + '">');
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

                // AJAX in the modal contents if they haven't already been loaded

                picker.load('/picker/datetime/display/' + encodeURIComponent(options.displayFormat) + '/database/' + encodeURIComponent(options.databaseFormat) + '/id/' + pickerId + '/value/' + originalValue, function () {

                    $('#pickerCalendar' + pickerId).data('dateDisplay', elementClicked.val());

                    // Capture closure of modal

                    $(document).on('closed.fndtn.reveal', '#picker' + pickerId, function () {

                        // Update the elements value with the selected date

                        var pickerDisplay = $('#pickerDisplay' + pickerId);
                        pickerDisplay.val($('#pickerCalendar' + pickerId).data('dateDisplay'));

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
