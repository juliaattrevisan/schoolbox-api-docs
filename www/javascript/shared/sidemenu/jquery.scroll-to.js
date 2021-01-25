(function() {

    /**
     * Side Menu Scroll-To-Top
     *
     * @param options
     */
    $.fn.scrollToTop = function(options) {
        options = options || {};

        var config = init(options, $(this));

        /* Decide whether this scrolls upon plugin init,
         * or whether it needs a human interaction
         */
        if (config.scrollOnInit) {
            executeScroll();
        } else {
            setupScrollToTop();
        }

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
                velocity: 500,

                /* Execute scroll upon init
                 * By default you need to click an element to activate it
                 */
                scrollOnInit: false,

                /* A cancel callback function
                 */
                cancel: function () { return false; }
            }

            return $.extend(defaults, options);
        }

        /**
         * Setup
         */
        function setupScrollToTop() {
            $(config.element).on('click', config.activator, function(event) {
                if (config.cancel.apply(this) == false) {
                    executeScroll();
                }
            });
        }


        /**
         * Actually perform the scroll
         */
        function executeScroll() {
            $('html, body').animate({
                scrollTop: $(config.scrollTo).offset().top
            }, config.velocity);
        }
    }
}(jQuery));