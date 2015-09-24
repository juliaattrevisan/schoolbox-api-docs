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
                url: '/service/attendance/processToggleAttendance',

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
            $('#' + config.element.attr('id')).on('click', config.attendanceActivator, function(event) {
                event.preventDefault();

                var button = $(this);

                var data = config.getFormData(button);

                $.post(config.url, data, function (data) {
                    if (data.error == undefined) {
                        if (button.text() == config.text.rsvp) {
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
            return button.parent('form').serialize();
        }
    }

    /**
     * Method for exporting a calendar link.
     *
     * @param token  The user's token
     * @param values A plain object, or list from jQuery.fn.serializeArray
     *
     * @return string
     */
    $.fn.getExportCalendarLink = function(token, values) {

        // If from serializeArray: convert to object
        if ($.isArray(values)) {
            var new_values = {};

            $.each(values, function(k,v) {
                new_values[v.name] = v.value;
            });

            values = new_values;
        }

        // Add token to object
        values.token = token;

        // remove unused fields
        if (values['export'] !== 'group') {
            delete values.group;
        }

        if (values['export'] !== 'campus') {
            delete values.campus;
        }

        return window.location.protocol+'//'+window.location.host+'/calendar/export.php?'+$.param(values);
    };
}(jQuery));