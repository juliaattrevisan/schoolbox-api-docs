(function() {

    /**
     * Semester/Subject selector element
     * Occurs in Submission area and Grades area
     */
    $.fn.contextSelector = function(options) {
        options = options || {};

        var config = init(options, $(this));

        apply();

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
                href: '',
                selector: {
                    semester: '#context-selector-semester',
                    nextSemester: '[data-interaction="next"]',
                    previousSemester: '[data-interaction="previous"]',
                    subject: '#context-selector-enrolled-subjects',
                    units: '[data-interaction="select_unit"]'
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Apply event listeners
         */
        function apply() {

            var semesterSelector = $(config.selector.semester);
            var unitSelectors    = $(config.selector.units);
            var nextLink         = $(config.selector.nextSemester);
            var previousLink     = $(config.selector.previousSemester);

            // bind changes to semester selector
            if (unitSelectors.length) {
                // If there are unit selectors, the semester selector acts as a show/hide for the
                // unit selector for that semester.
                semesterSelector.on('change', function() {
                    var currentUnitSelector = unitSelectors.filter('[data-semester="'+semesterSelector.val()+'"]');
                    currentUnitSelector.removeClass('hide');
                    unitSelectors.not(currentUnitSelector).addClass('hide');

                    // Also enable/disable next/previous
                    // Is last selected? disable previous
                    // Is first selected? disable next
                    var selected        = semesterSelector.find(':selected');
                    var isFirstSemester = (selected.next().length === 0);
                    var isLastSemester  = (selected.prev().length === 0);
                    if (isFirstSemester) {
                        previousLink.attr('disabled', '');
                    } else {
                        previousLink.removeAttr('disabled');
                    }
                    if (isLastSemester) {
                        nextLink.attr('disabled', '');
                    } else {
                        nextLink.removeAttr('disabled');
                    }
                });
                semesterSelector.trigger('change');

                // Also hijack the prev/next semester click events
                nextLink.on('click', function(event) {
                    event.preventDefault();
                    semesterSelector.find(':selected').prev().prop('selected', true);
                    semesterSelector.trigger('change');
                });
                previousLink.on('click', function(event) {
                    event.preventDefault();
                    semesterSelector.find(':selected').next().prop('selected', true);
                    semesterSelector.trigger('change');
                });
            } else {
                // Redirect to semester
                semesterSelector.on('change', function () {
                    var url = $(this).val();
                    var elems = [config.href, url];
                    var redirect = elems.join('/');
                    if (url) {
                        window.location = '/' + redirect;
                    }

                    return false;
                });
            }

            // bind changes to subject selector
            $(config.selector.subject).on('change', function () {
                var url = $(this).val();
                var elems = [config.href, url];
                var redirect = elems.join('/');

                if (url) {
                    window.location = '/' + redirect;
                }

                return false;
            });

            // Bind to unit selectors
            unitSelectors.on('change', function () {
                var url = $(this).val();
                var elems = [config.href, url];
                var redirect = elems.join('/');

                if (url) {
                    window.location = '/' + redirect;
                }

                return false;
            });

        }
    }
}(jQuery));