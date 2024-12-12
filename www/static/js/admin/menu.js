(function () {
    /**
     * AdminMenu
     *
     * Provide the slideDown menu or the Flyout menu depending on the
     * current dimensions of the viewport. Note that users can resize
     * their browser window and the menu should change accordingly
     *
     * @param options
     */
    $.fn.adminMenu = function () {
        // ensure the flyouts open on first selection, as sligeToggle checks for display:none to determine status
        $("[data-admin-subnav-flyout]").css({ display: "none" });

        $("[data-parent-item] > a").on("click", function (event) {
            // to expand menu items
            event.preventDefault();

            const $target = $(this)
                .closest("[data-parent-item]")
                .toggleClass("flyout-expanded") // add class to indicate it's expanded
                .find("[data-admin-subnav-flyout]")
                .slideToggle(); // toggle selected

            const inactiveFlyouts = $("[data-admin-subnav-flyout]").not($target);
            inactiveFlyouts.slideUp(); //and ensure all others are closed
            inactiveFlyouts.closest("[data-parent-item]").removeClass("flyout-expanded"); // remove the expanded class from all other parent items
        });

        // This is only available for the small viewport
        $("[data-admin-subnav-dropdown]").on("click", function (event) {
            // toggle the 'Go to' menu to expand the core menu
            event.preventDefault();

            $("[data-admin]").slideToggle();
        });
    };
})(jQuery);
