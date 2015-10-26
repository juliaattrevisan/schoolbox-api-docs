(function() {

    /**
     * A facade method for spawning timetables
     *
     * @param options
     */
    $.fn.timetableStart = function(options) {
        options = options || {};

        var config = init(options, $(this));

        showFirstTimetable();
        showSelectedTimetable();

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

                /* Reforged timetables end up inside this element
                 */
                target: '[data-smaller]',

                /* The menu items when you have multiple timetables
                 * to chooze from. A unique identifier, typically a
                 * user id
                 */
                selectors: {
                    data: '[data-timetable-selector]',
                    attr: 'data-timetable-selector'
                },

                /* Timetable wrapper element containing the heading
                 * and the timetable itself (they are separate elements)
                 */
                wrapper: {
                    data: '[data-timetable-wrapper]',
                    attr: 'data-timetable-wrapper'
                },

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
         * Show first timetable
         */
        function showFirstTimetable() {
            var selectedItem = $(config.selectors.data).first();

            updateMenu(selectedItem);

            showTimetable(selectedItem.attr(config.selectors.attr));
        }

        /**
         * Assemble the timetable for the selected person
         * Occurs when selecting a menu item from the person selector
         */
        function showSelectedTimetable() {
            $(config.selectors.data).click(function(event) {
                event.preventDefault();

                updateMenu($(this));

                showTimetable($(this).attr(config.selectors.attr));
            });
        }

        /**
         * Show the timetable associated with the chosen selector
         *
         * Show selected large timetable
         * Show selected small timetable
         *
         * @param selected
         */
        function showTimetable(selected) {
            var wrapper = $('['+config.wrapper.attr+'='+selected+']');
            var timetable = wrapper.find(config.timetable);

            wrapper.removeClass('hide').timetable();

            /* Adjust heights
             */
            timetable.timetableResize();

            /* Wait till resizing actually stops before resizing everything
             */
            $(window).bind('resizeEnd', function() {
                timetable.timetableResize();
            });
        }

        /**
         * Update person selector
         */
        function updateMenu(selectedItem) {

            /* Unselect all menu items
             * Reselect this one
             */
            $(config.selectors.data).parent().removeClass('active');
            $(selectedItem).parent().addClass('active');

            /* Hide all large timetables
             * Clear existing small timetables
             */
            $(config.wrapper.data).addClass('hide');
            $(config.target).html('')
        }
    }

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

            /* The timetable belongs to this person
             */
            var person = config.element.find('data-timetable-title');

            $(config.target).append(person);

            /* For every day, assemble a two column timetable
             */
            for (i = 1; i < numDays; i++) {
                var table = $('<table class="no-hover">');

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
}(jQuery));