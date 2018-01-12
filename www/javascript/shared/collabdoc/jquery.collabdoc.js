(function($) {
    $.fn.collabDoc = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupDimensions();

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
                width: '100%',
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Set up iframe dimensions upon init and resize
         */
        function setupDimensions() {

            /* Initial dimensions
             */
            $(config.element).css({
                width: config.width,
                height: $(window).height()*0.66
            });

            /* Resized dimensions
             */
            $(window).resize(function() {
                $(config.element).css({
                    width: config.width,
                    height: $(window).height()*0.66
                });
            });
        }
    }
}(jQuery));

