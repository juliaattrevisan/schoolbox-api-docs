(function($) {

    var closeMenus = function(exception) {

    };

    /**
     * Touch-friendly menu
     *
     * @param options
     */
    $.fn.touchMenu = function() {
        // Touch event anywhere outside the menu: close the whole menu

        var menu = $(this);

        // Touch start on a menu item: open it
        menu.on('touchstart', 'li', function(event) {
            //$(this).addClass('hover');
            // Does this have a link on it other than #?
            // Ignore if so.
            var target  = $(event.target).closest('a');
            var submenu = $(this).find('> ul');
            if (submenu.length && target.attr('href') !== '#') {
                // Still want to trigger menu flyout event, but not the visit of link.
                //event.preventDefault();

            }
        });

        menu.on('click', 'li', function(event) {
            event.preventDefault();
            //$(this).addClass('hover');
            // Does this have a link on it other than #?
            // Ignore if so.
            var target  = $(event.target).closest('a');
            console.log(event, target);
        });


        //$('body').on('touchstart', function(event) {
        //    if (event.isDefaultPrevented()) {
        //        menu.find('.hover').removeClass('hover');
        //    }
        //});
    }
})(jQuery);
