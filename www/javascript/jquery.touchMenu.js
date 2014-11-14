(function($) {

    var TouchMenu = function(element) {
        var self = this;
        self.element = element;

        // Touch start on a menu item: toggle it
        self.element.on('touchstart.touchMenu, pointerdown.touchMenu, MSPointerDown.touchMenu', 'li', function(event) {
            var item = $(this);

            //if (item.hasClass('hover')) {
            //    // Already hovered? Remove it, and allow the click event to trigger
            //    //self.closeMenus();
            //} else {
                // Add hover to the item I just touched
                item.addClass('hover');
                // Remove hovers on events I didn't touch
                self.closeMenus(item);

            // Counterintuitively required to allow presses on leaves to fire events
            event.stopPropagation();

                // Does this have a link on it other than #? Ignore it.
                // (this only triggers on the first press on this item.)
                var target  = $(event.target).closest('a');
                var submenu = item.find('> ul');
                if (submenu.length && target.attr('href') !== '#') {
                    item.one('click.touchMenu', function(innerEvent) {
                        innerEvent.preventDefault();
                    });
                }
            //}

        });
    }

    TouchMenu.prototype.closeMenus = function(exception) {
        if (arguments.length) {
            this.element.find('.hover')
                .not(exception)
                .not(exception.parents('.hover'))
                .removeClass('hover');
        } else {
            this.element.find('.hover').removeClass('hover');
        }
    };

    /**
     * Touch-friendly menu
     *
     * @param options
     */
    $.fn.touchMenu = function() {
        this.each(function() {
            var element = $(this);
            element.data('touchMenu', new TouchMenu(element));
        });
        return this;
    }
})(jQuery);
