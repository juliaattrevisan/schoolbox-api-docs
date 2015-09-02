(function() {

    /**
     * Timetable
     *
     * Takes a full timetable grid and crunches it down into
     * ones that will fit into smaller devices
     *
     * @param options
     */
    $.fn.timetableDashboard = function(options) {
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

                /* Reforged timetables end up inside this element
                 */
                target: '[data-smaller]'
            }

            var derived = {

                /* Island html
                 * Sorry, Doctor
                 */
                header: '<h2 class="subheader"></h2>',
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
            var periodNames = $(config.element).find('thead th');

            /* For every day, assemble a two column timetable
             */
            var table = $('<table>');

            /* Generate table rows
             */
            $.each(periodNames, function(index, element) {
                var tr = $('<tr></tr>');
                tr.append($(element).clone());
                tr.append(dataCellSelector(1, index+1).clone());

                table.append(tr);
            });

            /* Assemble island
             */
            var section = $(config.section).append(table);
            var island = $(config.island).append(header).append(section)

            /* Inject and add a separator in case you have multiple timetables
             */
            $(config.target).append(island);
        }

        /**
         * Select a cell in main timetable
         *
         * @param row
         * @param column
         */
        function dataCellSelector(row, column) {
            return $(config.element).find('tbody tr:nth-child('+row+') td:nth-child('+column+')').first();
        }
    }
}(jQuery));