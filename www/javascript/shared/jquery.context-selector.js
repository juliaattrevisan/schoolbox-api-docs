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
                    subject: '#context-selector-enrolled-subjects'
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

            // bind changes to semester selector
            $(config.selector.semester).bind('change', function () {
                var url = $(this).val();
                var prefix = '/' + config.href + '/';

                if (url) {
                    window.location = prefix + url;
                }

                return false;
            });

            // bind changes to subject selector
            $(config.selector.subject).bind('change', function () {
                var url = $(this).val();

                if (url) {
                    window.location = prefix + url;
                }

                return false;
            });
        }
    }
}(jQuery));