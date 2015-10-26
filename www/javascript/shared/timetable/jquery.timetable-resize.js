(function() {

    /**
     * Timeout on resize end
     */
    $.fn.timetableTimeout = function() {
        $(window).resize(function() {
            if (this.resizeTimeout) {
                clearTimeout(this.resizeTimeout);
            }

            this.resizeTimeout = setTimeout(function () {
                $(this).trigger('resizeEnd');
            }, 400);
        });
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

            /* Reflow all subjects first
             */
            $('td').find(config.subject).css({
                height: 'auto'
            });

            var indicatorOffset = 4;

            $.each($(config.element).find('tr'), function(index, row) {
                var rowHeight = $(row).height();
                var hasActiveSubject = false;

                /* If this row contains an instance of timetable-subject-active
                 * add extra height to every td, to compensate for Indicator
                 */
                if ($(row).find('.timetable-subject-active')) {
                    hasActiveSubject = true;
                }

                $.each($(row).find('td'), function(index, cell) {
                    var allSubjects = $(cell).find(config.subject);

                    /* Only one subject - make it fill available space
                     */
                    if (allSubjects.length == 1) {
                        if (hasActiveSubject && !$(cell).hasClass('timetable-subject-active')) {
                            allSubjects.css({
                                height: rowHeight + indicatorOffset + 'px'
                            });
                        }
                        else {
                            allSubjects.css({
                                height: rowHeight + 'px'
                            });
                        }
                    }
                    /* Distribute difference equally
                     */
                    else {
                        var subjectHeight = 0;
                        var diff = 0;

                        $.each(allSubjects, function(index, subject) {
                            subjectHeight += $(subject).height();
                        });

                        /* How much to add per subject
                         */
                        if (hasActiveSubject && !$(cell).hasClass('timetable-subject-active')) {
                            diff = (rowHeight - subjectHeight + indicatorOffset)/allSubjects.length;
                        }
                        else {
                            diff = (rowHeight - subjectHeight)/allSubjects.length;
                        }

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