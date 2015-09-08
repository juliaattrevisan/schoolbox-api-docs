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

                /* Executes after Ready
                 */
                afterReady: function() {
                    defaultAfterReady();
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
        function defaultAfterReady() {
            var dialog = $(config.exportElement);
            var dialogUrl = $(config.exportUrl);

            /* Bypass foundation bug
             * https://github.com/zurb/foundation/issues/4746
             */
            dialog.foundation('reveal', 'open');

            dialog.on('opened', function() {
                dialogUrl.select();
            });
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
                    $(config.exportUrl).text($.getExportCalendarLink(
                        config.userToken,
                        {
                            'export': 'group',
                            'group': config.groupId
                        }
                    ));

                    button.show();
                });

                config.afterReady();
            });
        }
    }

    /**
     * Set up attendance processing
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
            var selector = '#' + config.element.attr('id') + ' ' + config.attendanceActivator;

            $(document).on('click', selector, function (event) {
                event.preventDefault();

                var button = $(this);

                var data = button.parent('form').serialize();

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
    }
}(jQuery));