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
                velocity: 500,

                cancel: function () { return false; }
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
            $(config.element).on('click', config.activator, function(event) {
                if (config.cancel.apply(this) == false) {
                    $('html, body').animate({
                        scrollTop: $(config.scrollTo).offset().top
                    }, config.velocity);
                }
            });
        }
    }
}(jQuery));