
/**
 * This puts content into a textarea which can be copied to clipboard.
 * The textarea typically appears inside a modal, triggered by Foundation
 *
 * Originally made for Calendar Export urls, but repurposed into a more
 * generic usage
 *
 * $.fn.calendarExport({
 *     userToken: '{USER_TOKEN}'
 *     groupId: '{GROUP_ID}'
 * });
 *
 * @param options
 */
$.fn.clipboardSelector = function(options) {
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
            },

            /* Executes when ZeroClipboard is ready.
             * This won't always happen, because it relies on Flash
             * (whose presence isn't reliable!)
             */
            onZeroClipboardReady: function() {}
        }

        var derived = {
        }

        var merged = $.extend(defaults, derived, options);

        return merged;
    }

    /**
     * Default After Ready function
     *
     * Calendar export urls is the default, but you can make your own
     * function for custom behaviours
     */
    function defaultOnReady() {
        var dialogUrl = $(config.exportUrl);

        dialogUrl.text($.fn.getExportCalendarLink(
            config.userToken,
            {
                'export': 'group',
                'group': config.groupId
            }
        ));

        $(config.copy).show();

        /* Bypass foundation bug
         * https://github.com/zurb/foundation/issues/4746
         */
        $(config.exportElement).on('opened', function() {
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

            config.onReady();
            client.on('ready', function() {
                config.onZeroClipboardReady();
            });
        });
    }
}