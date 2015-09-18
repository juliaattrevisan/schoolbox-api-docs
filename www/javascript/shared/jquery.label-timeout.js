(function() {
    "use strict";

    /**
     * Shows a label in its initial waiting state, before any
     * ajax callbacks are completed
     *
     * @param options
     */
    $.fn.labelActivate = function(options) {
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

                /* Initial text to display
                 */
                text: 'Updating...please wait',

                /* The style at the end of labelActivate
                 */
                css: 'secondary'
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
            $(config.element)
                .text(config.text)
                .removeClass('alert success hide')
                .addClass(config.css)
                .css({opacity:1});
        }
    }

    /**
     * Fades out a label.
     * This occurs within an ajax success/error callback
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
                fadeDuration: 1000,

                /* Text to display when callback completes
                 */
                text: 'Updated',

                /* The style at the end of labelActivate
                 */
                initialCss: 'secondary',

                /* The style to use after labelActivate
                 */
                css: 'success'

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
            $(config.element)
                .text(config.text)
                .removeClass(config.initialCss)
                .addClass(config.css)

            setTimeout(
                function() {
                    $(config.element).animate({opacity: 0}, config.fadeDuration, function() {
                        $(config.element)
                            .addClass('hide')
                            .removeClass(config.css)
                            .addClass(config.initialCss)
                            .trigger('hide.labelTimeout');
                    });
                },
                config.duration
            )
        }
    };
}(jQuery));