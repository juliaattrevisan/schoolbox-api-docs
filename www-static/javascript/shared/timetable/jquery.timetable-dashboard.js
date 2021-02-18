(function() {

    /**
     * A facade method for spawning timetables
     *
     * @param options
     */
    $.fn.timetableStart = function(options) {
        options = options || {};

        var config = init(options, $(this));

        showTimetable();

        /* Set up event listener for resizeEnd
         */
        $.fn.timetableTimeout();

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

                /* This indicates the timetable table itself
                 */
                timetable: '[data-timetable]'
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Show the timetable
         *
         * Show selected large timetable
         * Show selected small timetable
         */
        function showTimetable() {
            var timetable = $(config.timetable);

            timetable.timetableRow();

            /* Adjust heights
             */
            timetable.timetableResize();

            /* Wait till resizing actually stops before resizing everything
             */
            $(window).bind('resizeEnd', function() {
                timetable.timetableResize();
            });
        }
    }

    /**
     * Timetable
     *
     * Takes a Day row grid and crunches it down into
     * ones that will fit into smaller devices
     *
     * @param options
     */
    $.fn.timetableRow = function(options) {
        options = options || {};

        var config = init(options, $(this));

        assemble();

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

                container: '[data-timetable-container]',
                header: '[data-timetable-header]',

                /* Reforged timetables end up inside this element
                 */
                target: '[data-smaller]'
            }

            var derived = {

                /* Island html
                 * Sorry, Doctor
                 */
                island: '<div class="small-12 island no-pad"></div>',
                section: '<section></section>'
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Assemble a timetable
         */
        function assemble() {
            $(config.element).each(function() {
                var timetable   = $(this),
                    container   = timetable.closest(config.container),
                    header      = timetable.closest(config.header),
                    target      = container.find(config.target),
                    periodNames = timetable.find('thead th');

                /* For every day, assemble a two column timetable
                 */
                var table = $('<table class="no-hover">');

                /* Generate table rows
                 */
                $.each(periodNames, function(index, element) {
                    var tr = $('<tr></tr>');
                    tr.append($(element).clone());
                    tr.append(dataCellSelector(timetable, 1, index+1).clone());

                    table.append(tr);
                });

                /* Assemble island
                 */
                var island = $(config.island).append(header).append(table);

                /* Inject and add a separator in case you have multiple timetables
                 */
                target.append(island);
            });
        }

        /**
         * Select a cell in main timetable
         *
         * @param table
         * @param row
         * @param column
         */
        function dataCellSelector(table, row, column) {
            return table.find('tbody tr:nth-child('+row+') td:nth-child('+column+')').first();
        }
    }
}(jQuery));
