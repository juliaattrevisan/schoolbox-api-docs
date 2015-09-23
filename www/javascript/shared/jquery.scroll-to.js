(function() {

    /**
     * Side Menu Scroll-To-Top
     *
     * @param options
     */
    $.fn.scrollToTop = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupScrollToTop();

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

                /* Element within config.element which
                 * triggers scroll
                 */
                activator: 'a',

                /* Scroll to top of this element
                 */
                scrollTo: '[data-side-nav]',

                /* Animation velocity
                 */
                velocity: 500
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Setup
         */
        function setupScrollToTop() {
            $(config.element).find(config.activator).click(function(event) {
                $('html, body').animate({
                    scrollTop: $(config.scrollTo).offset().top
                }, config.velocity);
            });
        }
    }
}(jQuery));