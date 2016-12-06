$.fn.alaressDateTimePicker = function (options) {

    // Keep an array of the flatpickr instances

    window.calendarInstance = [];

    // Counter for uniquely identifying these things

    window.alaressDateTimePickerCount = 0;

    // Iterate all the elements that want datetime pickers

    var elements = this;

    elements.each(function (index, element) {

        var id = window.alaressDateTimePickerCount;
        var idWrapper = 'alaressDateTimePicker_' + id;
        var idCalendar = 'alaressDateTimePickerCalendar_' + id;

        // Insert the HTML for the datetime picker modal

        $(element).after(
            '<div data-reveal id="' + idWrapper + '" class="reveal-modal">' +
                '<div class="row">' +
                    '<div class="small-12 medium-4 island">' +
                        '<section>' +
                            '<div class="content">' +
                                '<h2>Select Date/Time</h2>' +
                                '<div id="' + idCalendar + '"></div>' +
                            '</div>' +
                        '</section>' +
                    '</div>' +
                    '<div class="small-12 medium-4 island">' +
                        '<section>' +
                            '<div class="content">' +
                                '<h2>Students</h2>' +
                                '<ul class="information-list">' +
                                    '<li class="actions-small-1">' +
                                        '<div class="list-item">' +
                                            '<div class="card small-12">' +
                                                '<a href="#">' +
                                                    '<img src="/portrait.php?userId=135&amp;size=square64" width="48">' +
                                                    '<h3>Sub Zero</h3>' +
                                                '</a>' +
                                                '<p class="meta">' +
                                                    '3 Events Scheduled' +
                                                '</p>' +
                                            '</div>' +
                                        '</div>' +
                                        '<nav>' +
                                            '<a class="icon-next no-text" href="#" title="Modify"></a>' +
                                        '</nav>' +
                                    '</li>' +
                                '</ul>' +
                            '</div>' +
                        '</section>' +
                    '</div>' +
                    '<div class="small-12 medium-4 island">' +
                        '<section>' +
                            '<div class="content">' +
                                '<h2>Scheduled Events &amp; Work</h2>' +
                            '</div>' +
                        '</section>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );


        // Store the ID of the datetime picker on the element for use in the click event

        $(element).data('alaressDateTimePickerId', id);

        // Create click event to open the modal for the element

        $(element).click(function () {

            // Set the flatpickr to the date currently shown in the textbox

            window.calendarInstance[$(this).data('alaressDateTimePickerId')].setDate($(element).val());

            // Display the datetime picker modal

            $('#alaressDateTimePicker_' + $(this).data('alaressDateTimePickerId')).foundation('reveal', 'open');
        });

        // Setup flatpickr calendar

        var optionsCurrent = {
            defaultDate: $(element).val(),
            inline: true,
            dateFormat: 'Y-m-d H:i',
            enableTime: true,

            onChange: function (selectedDates, dateString, instance) {
                // Write the chosen value back to the element

                instance.config.element.val(dateString);
            }
        };

        // Put in user override options

        for (var attribute in options) {
            optionsCurrent[attribute] = options[attribute];
        }

        window.calendarInstance[id] = flatpickr("#" + idCalendar, optionsCurrent);

        // Keep a link back to the element it needs to update

        window.calendarInstance[id].set('element', $(element));

        // Increment global counter

        window.alaressDateTimePickerCount++;
    });
};
