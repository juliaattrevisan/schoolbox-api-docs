(function() {
    "use strict";

    /**
     * Shows a label in its initial waiting state, before any
     * ajax callbacks are completed
     *
     * @param options
     */
    $.fn.toastActivate = function(options) {
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

                /* The style at the end of toastActivate
                 */
                css: 'highlight'
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
                .stop()
                .text(config.text)
                .removeClass('alert success')
                .addClass('highlight pop');
        }
    }

    /**
     * Fades out a label.
     * This occurs within an ajax success/error callback
     */
    $.fn.toastTimeout = function(options) {
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
                duration: 2000,

                /* Duration of the fade effect for the message
                 * element to go away
                 */
                fadeDuration: 1000,

                /* Text to display when callback completes
                 */
                text: 'Updated',

                /* The style at the end of toastActivate
                 */
                initialCss: 'highlight',

                /* The style to use after toastActivate
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
                .stop();

            setTimeout(
                function() {
                    $(config.element)
                        .addClass(config.initialCss)
                        .removeClass('pop')
                        .trigger('toastTimeout');
                },
                config.duration
            )
        }
    };
}(jQuery));