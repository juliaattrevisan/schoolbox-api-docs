/**
 * Artificial Print Button
 */
(function() {

    /**
     * Artificial Print Button
     *
     * @param options
     */
    $.fn.artificialPrint = function(options) {
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
                element: element
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Apply javascript to trigger Print artificially
         */
        function apply() {
            $(config.element).click(function(event) {
                event.preventDefault();
                window.print();
                return false;
            });
        }
    }
})(jQuery);