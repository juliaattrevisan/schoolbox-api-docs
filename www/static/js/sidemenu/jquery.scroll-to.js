(function () {
    /**
     * Side Menu Scroll-To-Top
     *
     * @param options
     */
    $.fn.scrollToTop = function (options) {
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
                activator: "a",

                /* Scroll to top of this element
                 */
                scrollTo: "[data-side-nav]",

                /* Animation velocity
                 */
                velocity: 500,

                /* Execute scroll upon init
                 * By default you need to click an element to activate it
                 */
                scrollOnInit: false,

                /* A cancel callback function
                 */
                cancel: function () {
                    return false;
                },
            };

            return $.extend(defaults, options);
        }

        /**
         * Setup
         */
        function setupScrollToTop() {
            $(config.element).on("click", config.activator, function (event) {
                if (config.cancel.apply(this) == false) {
                    executeScroll();
                }
                /**
                 * We also need to scrollTop the previous submenu to ensure the new submenu is visible.
                 * This is because the previous submenu height could exceed the viewport, and the new submenu
                 * could be shorter, so wouldn't be visible if the user scrolled to the bottom of the previous submenu.
                 */
                const subMenu = event.target.closest("[data-sub-menu]");
                if (subMenu && subMenu.scrollTop > 0) {
                    subMenu.scrollTop = 0;
                }
            });
        }

        /**
         * Actually perform the scroll
         */
        function executeScroll() {
            $("html, body").animate(
                {
                    scrollTop: $(config.scrollTo).offset().top,
                },
                config.velocity,
            );
        }
    };
})(jQuery);
