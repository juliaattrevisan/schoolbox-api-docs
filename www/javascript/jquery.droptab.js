/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
;
(function ($, undefined) {

    var pluginName = 'droptab',
        noop       = function () {};

    var defaults = {
        buttonSelector: null,
        tabSelector: '> *',
        hoverClass: 'hover',
        openClass: 'open',
        dataAttr: 'tab',
        onOpen: noop,
        onClose: noop
    };

    function Droptab(element, options) {
        var self = this;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);

        if (this.options.buttonSelector == null) {
            throw Error("Button selector must be defined");
        }

        this.buttons = $(this.options.buttonSelector);
        this.tabs    = $(this.element).find(this.options.tabSelector);

        this.buttons.on('click', function (event) {
            var hover  = self.options.hoverClass,
                open   = self.options.openClass,
                attr   = self.options.dataAttr,
                openMe = true;

            event.preventDefault();

            if ($(this).hasClass(hover)) {
                openMe = false;
            }

            // Set hover classes, and remove it from other buttons
            $(this).toggleClass(hover, openMe);
            self.buttons.not(this).removeClass(hover);

            // Close all other tabs, and open this tab if needed
            self.tabs.removeClass(open);

            if (openMe) {
                $('#'+$(this).data(attr)).addClass(open);
                self.options.onOpen.apply(this);
            } else {
                self.options.onClose.apply(this);
            }
        });
    }


    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new Droptab(this, options));
            }
        });
    }

})(jQuery);