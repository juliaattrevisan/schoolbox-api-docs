/**
 * Decide whether the calendar is being displayed on a
 * device with a small screen. fullcalendar requires
 * two different mutually exclusive configuration settings
 * to display properly on both desktop and mobile devices
 *
 * 40em marks Small breakpoint
 * Multiply by 16px base font size, js doesn't support em units
 *
 * @returns {boolean}
 */
function isCalendarSmall() {
    if ($(window).width() <= 40*16) {
        return true;
    }

    return false;
}

(function() {

    /**
     * Set up calendar export dialog
     *
     * $.fn.calendarExport({
     *     userToken: '{USER_TOKEN}'
     *     groupId: '{GROUP_ID}'
     * });
     *
     * @param options
     */
    $.fn.calendarExport = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupExport();

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

                /* User Token (phplib)
                 */
                userToken: '',

                /* GroupID (phplib)
                 */
                groupId: '0',

                /* Clicking this element spawns a dialog
                 */
                exportActivator: '#calendar-export',

                /* An element which copies contents to clipboard
                 */
                copy: '#copy-button',

                /* The dialog element
                 */
                exportElement: '#calendar-export-dialog',

                /* The element containing the export url
                 */
                exportUrl: '#calendar-export-dialog-url',

                /* Executes when Ready
                 */
                onReady: function() {
                    defaultOnReady();
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Default After Ready function
         */
        function defaultOnReady() {
            var dialog = $(config.exportElement);
            var dialogUrl = $(config.exportUrl);

            dialogUrl.text($.getExportCalendarLink(
                config.userToken,
                {
                    'export': 'group',
                    'group': config.groupId
                }
            ));

            $(config.copy).show();

            dialogUrl.select();
        }

        /**
         * Set up export
         *
         * Form listener for exporter - brings up a dialog box that offers a
         * URL to click.
         */
        function setupExport() {
            $(config.exportActivator).click(function(event) {
                event.preventDefault();

                // Copy and paste button - show only when ready
                var button = $(config.copy).hide();
                var client = new ZeroClipboard(button);

                client.on('ready', function() {
                    config.onReady();
                });
            });
        }
    }

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
}(jQuery));