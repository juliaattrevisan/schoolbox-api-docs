(function() {

    $.fn.timetableStart = function(options) {
        options = options || {};

        var config = init(options, $(this));

        showFirstTimetable();
        showSelectedTimetable();

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
                 * to chooze from
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
         * Assemble the timetable for the selected person
         */
        function showSelectedTimetable() {

            /* When selecting a menu item from the person selector
             */
            $(config.selectors.data).click(function(event) {
                event.preventDefault();

                updateMenu($(this));

                /* Show selected large timetable
                 * Show selected small timetable
                 */
                var selected = $(this).attr(config.selectors.attr);
                var wrapper = $('['+config.wrapper.attr+'='+selected+']');
                var timetable = wrapper.find(config.timetable);

                wrapper.removeClass('hide').timetable({
                    target: config.target
                });

                /* Adjust heights
                 */
                timetable.timetableResize();
            });
        }

        /**
         * Show first timetable
         */
        function showFirstTimetable() {
            var selectedItem = $(config.selectors.data).first();

            updateMenu(selectedItem);

            var selected = selectedItem.attr(config.selectors.attr);
            var wrapper = $('['+config.wrapper.attr+'='+selected+']');
            var timetable = wrapper.find(config.timetable);

            wrapper.removeClass('hide').timetable({
                target: config.target
            });

            /* Adjust heights
             */
            timetable.timetableResize();
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

//    /**
//     * Set up timetable subject resizer
//     */
//    $.fn.timetableSetupResize = function(options) {
//        options = options || {};
//
//        var config = init(options, $(this));
//
//        setup();
//
//        /**
//         * Init
//         *
//         * @param options
//         * @param element
//         * @returns {*}
//         */
//        function init(options, element) {
//            var defaults = {
//                element: element,
//
//                /* A timetable table element
//                 */
//                timetable: '[data-timetable]',
//
//                /* Subject elements
//                 */
//                subject: '[data-timetable-subject]'
//            }
//
//            var derived = {
//            }
//
//            var merged = $.extend(defaults, derived, options);
//
//            return merged;
//        }
//
//        /**
//         * Set up event listeners
//         *
//         * Wait till resizing actually stops before resizing everything as the
//         * resizing process takes a little while
//         */
//        function setup() {
//            /* Run it once to start with
//             */
//            $.fn.timetableResize({
//                timetable: config.timetable,
//                subject: config.subject
//            });
//
//            /* Set up event listener for resizeEnd
//             */
//            $(window).resize(function() {
//                if (this.resizeTimeout) {
//                    clearTimeout(this.resizeTimeout);
//                }
//
//                this.resizeTimeout = setTimeout(function () {
//                    $(this).trigger('resizeEnd');
//                }, 1000);
//            });
//
//            /* Wait till resizing actually stops before resizing everything
//             */
//            $(window).bind('resizeEnd', function() {
//                $.fn.timetableResize({
//                    timetable: config.timetable,
//                    subject: config.subject
//                });
//            });
//        }
//    }

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

                /* A timetable table element, typically denoted by '[data-timetable]'
                 */
                element: element,

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
            $.each($(config.element).find('tr'), function(index, row) {
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