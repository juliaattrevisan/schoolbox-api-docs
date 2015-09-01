(function() {

    /**
     * Timetable
     *
     * Takes a full timetable grid and crunches it down into
     * ones that will fit into smaller devices
     *
     * @param options
     */
    $.fn.timetable = function(options) {
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
            var periodNames = $(config.element).find('tbody th');
            var numDays = $(config.element).find('thead th').length;
            var i;

            /* For every day, assemble a two column timetable
             */
            for (i = 1; i < numDays; i++) {
                var table = $('<table>');

                /* Generate table rows
                 */
                $.each(periodNames, function(index, element) {
                    var tr = $('<tr></tr>');
                    tr.append($(element).clone());
                    tr.append(dataCellSelector(index+1, i+1).clone());

                    table.append(tr);
                });

                /* Assemble island
                 */
                var header = $(config.header).append(headerCellSelector(i+1).html());
                var section = $(config.section).append(table);
                var island = $(config.island).append(header).append(section)

                /* Inject and add a separator in case you have multiple timetables
                 */
                $(config.target).append(island);
            }

            $(config.target).append('<hr />');
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

        /**
         * Select a header column
         *
         * @param column
         * @returns {*|jQuery}
         */
        function headerCellSelector(column) {
            return $(config.element).find('thead tr:first() th:nth-child('+column+')').first();
        }
    }

    /**
     * Resize all subject elements in a particular timetable
     *
     * @param options
     */
    $.fn.timetableResize = function(options) {
        options = options || {};

        var config = init(options, $(this));

        resize();

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

                /* A timetable table element
                 */
                timetable: '[data-timetable]',

                /* Subject elements
                 */
                subject: '[data-timetable-subject]'
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Resize all subject elements
         */
        function resize() {
            $.each($(config.timetable).find('tr'), function(index, row) {
                var rowHeight = $(row).height();

                $.each($(row).find('td'), function(index, cell) {
                    var allSubjects = $(cell).find(config.subject);

                    /* Only one subject - make it fill available space
                     */
                    if (allSubjects.length == 1) {
                        allSubjects.css({
                            height: rowHeight + 'px'
                        });
                    }
                    /* Distribute difference equally
                     */
                    else {
                        var subjectHeight = 0;

                        $.each(allSubjects, function(index, subject) {
                            subjectHeight += $(subject).height();
                        });

                        /* How much to add per subject
                         */
                        var diff = (rowHeight - subjectHeight)/allSubjects.length;

                        $.each(allSubjects, function(index, subject) {
                            $(subject).css({
                                height: $(subject).height() + diff + 'px'
                            });
                        });
                    }
                });
            });
        }
    }
}(jQuery));