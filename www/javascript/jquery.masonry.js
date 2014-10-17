/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
;
(function ($, window, document, undefined) {

    var pluginName = 'masonry',
        noop = function () {};

    var defaults = {
        // box that contains the options to drag into play
        palette: {
            selector: null,
            cursor: 'move',
            disabledCursor: 'default',
            disabledClass: 'disabled',
            clickToInsert: true,
            dragToInsert: true
        },

        paletteSelector: null,
        paletteHover: 'move',
        paletteDisabledCursor: 'default',
        disabledClass: 'disabled',

        persistUrl: null, // if unset, will use $.closest('form') to find a URL
        persistSuccessCallback: noop,
        persistFailureCallback: noop,


        dictPaletteDisabled: 'This component can only be used once',
        dictPlaceholder: 'You can drag components into this space'
    };

    function Masonry(element, options) {
        var self = this;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);
    }


    $.fn[pluginName] = function (options) {
        return this.each(function () {
            if (!$.data(this, 'plugin_' + pluginName)) {
                $.data(this, 'plugin_' + pluginName, new Masonry(this, options));
            }
        });
    }

})(jQuery, window, document);