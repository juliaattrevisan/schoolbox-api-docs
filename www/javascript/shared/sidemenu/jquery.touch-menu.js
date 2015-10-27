/**
 * Plugin for making hover menus touch-friendly.
 *
 * Relies on styles for class "hover" mirroring pseudo-selector :hover exactly.
 *
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
(function($) {
    "use strict";

    /**
     * @param element The menu to apply touch events to.
     * @constructor
     */
    var TouchMenu = function(element) {
        var self     = this;
        self.element = element;

        // TouchMenu is redundant when using Foundation's off-canvas
        if (self.element.closest('.left-off-canvas-menu, .right-off-canvas-menu').length) {
            return;
        }

        // Touch start on a menu item: toggle it
        self.element.on('touchstart.touchMenu, pointerdown.touchMenu, MSPointerDown.touchMenu', 'li', function(event) {
            var item = $(this);

            // Ignore this touch totally if this is a mouse event
            if (event.originalEvent.pointerType === 'mouse') {
                return;
            }

            if (item.hasClass('hover')) {
                // Already hovered? Remove the hover, and don't prevent the
                // link in the menu from triggering.
                self.closeMenus();
            } else {
                // Add hover to the item I just touched
                item.addClass('hover');
                // Remove hovers on events I didn't touch
                self.closeMenus(item);
                // Counterintuitively required to allow presses on leaves to fire events
                event.stopPropagation();

                // If this menu item has a submenu, prevent its click event from firing,
                // just this once.
                if (item.find('> ul').length) {
                    item.one('click.touchMenu', function(innerEvent) {
                        innerEvent.preventDefault();
                    });
                }
            }
        });

        // Touch anywhere outside this menu? close it.
        $(document).on('touchstart.touchMenu, pointerdown.touchMenu, MSPointerDown.touchMenu', function(event) {
            if (!$.contains(self.element[0], event.target)) {
                self.closeMenus();
            }
        });
    }

    /**
     * Closes touch menus.
     *
     * @param exception If supplied, doesn't close this menu.
     */
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

    $.fn.touchMenu = function() {
        this.each(function() {
            var element = $(this);
            element.data('touchMenu', new TouchMenu(element));
        });
        return this;
    }
})(jQuery);
