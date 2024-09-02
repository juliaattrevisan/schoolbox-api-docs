/* Implmentation of Schoolbox flyout menus */

(function ($) {
    $.fn.flyout = function (options) {
        var settings = $.extend(
            {
                attr: "data-menu",
                element: "li",
            },
            options,
        );

        return this.each(function () {
            var btn = $(this);
            var html = $("html");
            var menuref = "#" + btn.attr(settings.attr);
            var list = btn.parent(settings.element);

            var fn = function (event) {
                // if we clicked somewhere on the menu, just reattach the listener
                if ($(event.target).closest(menuref).length == 1) {
                    html.one("click", fn);
                }
                // we clicked elsewhere (including the button itself), so close the flyout
                else {
                    list.removeClass("open");
                }
            };

            // attach the listener
            btn.on("click", function (e) {
                e.preventDefault();
                // is already open
                if (list.hasClass("open")) {
                    // we've clicked on the button, which is already open - the html listener should close it for us.....
                    return true;
                }

                // trigger a click on the document, so that if a flyout is open it gets closed
                html.trigger("click");

                // is not open: attach a close listener for this
                html.one("click", fn);

                // open it
                list.addClass("open");

                return false;
            });
        });
    };
})(jQuery);
