(function () {
    /**
     * AdminMenu
     *
     * Provide a slide down menu interface for the admin area
     * Only one menu item can be expanded at a time
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

        // If there's a tab that is currently selected on page load, expand the parent menu item
        $("[data-parent-item].active").addClass("flyout-expanded").find("[data-admin-subnav-flyout]").slideDown(0);
    };
})(jQuery);
