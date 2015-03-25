(function() {

    /**
     * Constructor
     *
     * @param options
     */
    $.fn.preview = function(options) {
        options = options || {}

        /* Store the config in this instance so private methods
         * can access it
         */
        var config = init(options, this);

        $(config.element).click(function(event) {
            event.preventDefault();

            $.get($(this).attr('href'), function(responseText) {
                console.log(responseText);
            });
        });

        /**
         * Initialise parameters
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
    }
}(jQuery));