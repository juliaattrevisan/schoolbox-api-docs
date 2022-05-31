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

        /**
         * We should ensure each flyout menu is fully visible when active, so the 
         * user doesn't have to scroll further down the page to see all items
         */
        const flyoutMenuList = document.querySelectorAll('[data-admin-subnav-flyout]');
        const defaultMarginTop = 35;
        const extraPadding = 20;
        const intersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.intersectionRatio < 1 && entry.intersectionRatio !== 0) { //is partially visible and not just display: none
                    //shift the menu up the page so it's fully visible
                    entry.target.style.marginTop = `-${(1-entry.intersectionRatio) * entry.boundingClientRect.height + defaultMarginTop + extraPadding}px`;
                } else { //otherwise reset it to default marginTop
                    entry.target.style.marginTop = `-${defaultMarginTop}px`;
                }
            });
        });

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
                element: element,

                /* The breakpoint threshold between small viewport menu and regular flyout menu
                 * Consult www/static/css/core/_settings.scss:90 for the var definition
                 * You'll have to keep these in sync manually, sorry
                 */
                threshold: '951px'
            };

            return $.extend(defaults, options);
        }


        /**
         * Decide which menu interface to enable for the user, depending on their
         * current viewport dimension at the time of execution
         */
        function chooseMenuStrategy() {
            let mq = window.matchMedia( "(max-width: " + config.threshold + ")" );

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

                /**
                 * Stop tracking the flyout menus and ensure the marginTop is reset for each
                 */
                 flyoutMenuList.forEach((flyoutMenu) => {
                    intersectionObserver.unobserve(flyoutMenu);
                    flyoutMenu.style.marginTop = `-${defaultMarginTop}px`;
                });

            } else {

                /* This is the flyout menu which should become available again
                 */
                $('[data-admin-subnav-flyout]').removeAttr('style');

                /**
                 * Ensure the flyout menu is fully visible when active
                 */
                flyoutMenuList.forEach((flyoutMenu) => {
                    intersectionObserver.observe(flyoutMenu);
                });

            }
        }
    }
}(jQuery));
