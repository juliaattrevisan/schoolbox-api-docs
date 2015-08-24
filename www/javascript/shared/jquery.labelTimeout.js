(function() {
    "use strict";

    /**
     * Fades out a label.
     */
    $.fn.labelTimeout = function(options) {
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

                /* Duration to display the message element
                 */
                duration: 1000,

                /* Duration of the fade effect for the message
                 * element to go away
                 */
                fadeDuration: 1000
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Apply animation
         */
        function apply() {

            /* Reset values when triggering repeatedly
             */
            $(config.element).removeClass('hide').css({opacity:1});

            setTimeout(
                function() {
                    $(config.element).animate({opacity: 0}, config.fadeDuration, function() {
                        $(config.element).addClass('hide');
                    });
                },
                config.duration
            )
        }
    };
}(jQuery));
