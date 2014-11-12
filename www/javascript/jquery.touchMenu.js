(function($) {

    var TouchMenu = function(element) {
        var self = this;
        self.element = element;

        // Make sure all of these hovers go away when an actual mouseenter event occurs
        self.element.on('mouseenter.touchMenu', function(event) {
            self.closeMenus();
        });

        // Touch start on a menu item: toggle it
        self.element.on('touchstart.touchMenu, pointerdown.touchMenu, MSPointerDown.touchMenu', 'li', function(event) {
            var item = $(this);

            if (item.hasClass('hover')) {
                // Already hovered? remove it
                self.closeMenus();
            } else {
                // Add hover to the item I just touched
                item.addClass('hover');
                // Remove hovers on events I didn't touch
                self.closeMenus(item);
            }

            event.stopPropagation();
            event.preventDefault();

            // Does this have a link on it other than #?
            // Ignore if so.
            var target  = $(event.target).closest('a');
            var submenu = $(this).find('> ul');
            if (submenu.length && target.attr('href') !== '#') {
                item.one('click.touchMenu', function(innerEvent) {
                    innerEvent.preventDefault();
                });
            }
        });
    }

    TouchMenu.prototype.closeMenus = function(exception) {
        this.element.find('.hover')
            .not(exception)
            .not(exception.parents('.hover'))
            .removeClass('hover');
    };

    /**
     * Touch-friendly menu
     *
     * @param options
     */
    $.fn.touchMenu = function() {
        var elements = this;
        elements.each(function() {
            $(this).data('touchMenu', new TouchMenu($(this)));
        });

        // Apply the body handler
        var touchStartHandler = function(event) {
            if (event.namespace != 'touchMenu') {
                elements.each(function() {
                    $(this).data('touchMenu').closeMenus();
                })
            }
        };
        $('body').on('touchstart, pointerdown, MSPointerDown', touchStartHandler);
        return elements ;
    }
})(jQuery);
