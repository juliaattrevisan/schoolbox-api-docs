(function() {

    /**
     * AdminMenu
     *
     * Provide the slideDown menu or the Flyout menu depending on the
     * current dimensions of the viewport. Note that users can resize
     * their browser window and the menu should change accordingly
     *
     * @param options
     */
    $.fn.adminMenu = function(options) {
        options = options || {};

        let config = init(options, $(this));
        let resizeTimeout = false;

        /* Whenever the viewport dimension changes, run this evaluation
         * The viewport could start large and become small, or vice versa,
         * so on resize you need to re-evaluate what strategy to use
         */
        $(window).resize(function() {
            if (resizeTimeout) {
                clearTimeout(resizeTimeout);
            }

            /* resize() occurs while you're resizing the window via dragging
             * but we only care when you're finished resizing
             */
            resizeTimeout = setTimeout(function () {
                $(this).trigger('resizeEnd');
            }, 400);
        });

        /* When you let go after resizing
         */
        $(window).bind('resizeEnd', function() {
            chooseMenuStrategy();
        });

        /* Run once initially
         */
        chooseMenuStrategy();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            let defaults = {
                element: element
            };

            return $.extend(defaults, options);
        }


        /**
         * Decide which menu interface to enable for the user, depending on their
         * current viewport dimension at the time of execution
         */
        function chooseMenuStrategy() {
            let mq = window.matchMedia( "(max-width: 950px)" );

            /* Clear any event bindings that might have been assigned previously
             */
            $("[data-parent-item] > a").unbind();
            $('[data-admin-subnav-dropdown]').unbind();

            /* Recalculate whether to use the small viewport approach or not
             */
            if (mq.matches) {

                /* This is the small viewport so you don't need the flyout menu
                 * You only need the slideDown menu
                 */
                $('[data-admin-subnav-flyout]').css({ display: 'none' }); //ensure the flyouts open on first selection, as sligeToggle checks for display:none to determine status

                $("[data-parent-item] > a").on('click', function(event) { //to expand menu items
                    event.preventDefault();

                    let $target = $(this).closest('[data-parent-item]').find("[data-admin-subnav-flyout]").slideToggle(); //toggle selected

                    $('[data-admin-subnav-flyout]').not($target).slideUp(); //and ensure all others are closed
                });

                /* This is only available for the small viewport
                 */
                $('[data-admin-subnav-dropdown]').on('click', function(event) { //toggle the 'Go to' menu to expand the core menu
                    event.preventDefault();

                    $('[data-admin]').slideToggle();
                });
            } else {

                /* This is the flyout menu which should become available again
                 */
                $('[data-admin-subnav-flyout]').css({ display: 'none' });
                $('[data-admin-subnav-flyout]').removeAttr('style');
            }
        }
    }
}(jQuery));
