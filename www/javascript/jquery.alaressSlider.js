/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
;(function ($, window, document, undefined) {

    // the name of the plugin, as internally referenced by $().data()
    var pluginName = 'alaressSlider';

    // default construction arguments
    var defaults = {};


    /**
     * Constructor.
     * A separate instance will exist on every jQuery
     * element upon which $().alaressSlider() is invoked.
     */
    function alaressSlider(element, options) {
        var alaressSlider = this;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);
    }

    /**
     * Example privileged method: accessible via:
     * - $().data('plugin_alaressSlider').examplePrivilegedMethod(val, val);
     * - $().alaressSlider('examplePrivileged', val, val);
     */
    alaressSlider.prototype.examplePrivilegedMethod = function (arg, another) {
        // `this` refers to the alaressSlider instance
        examplePrivateMethod(this.element);
    };

    /**
     * Private method restricted to plugin scope. Inaccessible from outside plugin.
     */
    function examplePrivateMethod(noTouchies) {
        window.console.log(noTouchies);
    }


    /**
     * Register plugin into jQuery
     *
     * This registration can be used in the following ways:
     *  - $().alaressSlider() to invoke the plugin in its default state
     *  - $().alaressSlider({object}) to instantiate OR UPDATE the plugin with customisations
     *  - $().alaressSlider('string') to call a prototyped method on the instance
     */
    $.fn[pluginName] = function (options) {
        var plugin = 'plugin_' + pluginName;

        if (typeof options === 'object' || typeof options === 'undefined') {
            // if this is configuration
            return this.each(function () {
                if (!$.data(this, plugin)) {
                    // initialise if not already
                    $.data(this, plugin, new alaressSlider(this, options));
                } else {
                    // just update existing config
                    setOptions.call($.data(this, plugin), options);
                }
            });
        } else if (typeof options === 'string') {
            // if this is a function request
            var rtn = [];

            this.each(function () {
                var obj = $(this).data(plugin);

                if (typeof obj === 'undefined') {
                    throw "alaressSlider not initialised";
                }

                if (options in obj) {
                    rtn.push(obj[method].call(obj));
                }
            });
            return $($.map(rtn, function (el) {
                return $.makeArray(el)
            }));
        }
    };


    /**
     * Expects to be applied to the alaressSlider object (but is not a prototype method so as to keep it private).
     *
     * @param {Object} opts
     */
    function setOptions(opts) {
        // if options are already defined, merge with that, otherwise defaults
        var merge = typeof this.options === 'undefined' ? defaults : this.options;

        // and apply the merged set
        this.options = $.extend(true, {}, merge, opts);
    }

})(jQuery, window, document);
