(function() {

    /**
     * Set up attendance processing
     *
     * This should be applied to the element containing the RSVP control
     * which is typically a modal div
     *
     * @param options
     */
    $.fn.calendarAttendance = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupAttendance();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* Clicking this element spawns a bubble
                 */
                attendanceActivator: '[data-attendance-request]',

                /* The controller to deal with the attendance
                 */
                url: '/service/attendance/setAttendance',

                /* Text strings for button label states
                 */
                text: {
                    rsvp: 'RSVP',
                    unattend: 'Unattend'
                },

                /* You can determine post data your own way
                 */
                getFormData: function(button) {
                    return defaultGetFormData(button);
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Set up attendances
         */
        function setupAttendance() {
            config.element.on('click', config.attendanceActivator, function(event) {
                event.preventDefault();

                var button = $(this);

                var data = config.getFormData(button);

                if (button.text() === config.text.rsvp) {
                    data['status'] = 'add';
                } else {
                    data['status'] = 'remove';
                }

                $.post(config.url, data, function (data) {
                    if (data.error == undefined) {
                        if (button.text() === config.text.rsvp) {
                            button.html(config.text.unattend);
                        }
                        else {
                            button.html(config.text.rsvp);
                        }
                    }
                });
            });
        }

        /**
         * Default way of getting post data
         *
         * @param button
         * @returns {*}
         */
        function defaultGetFormData(button) {
            return {
                event: {
                    id: button.parent('form').find('input[name="eventId"]').val()
                }
            };
        }
    }
}(jQuery));
