/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
;(function ($, window, document, Swiper, undefined) {
    "use strict";

    // the name of the plugin, as internally referenced by $().data()
    var pluginName = 'alaressSlider';

    // default construction arguments
    var defaults = {
        autoplay: false, // This will be overridden on 'lazyImageReady' below
        centeredSlides: true,
        loop: true,
        speed: 1000,
        lazy: {
            loadPrevNext: true,
            loadPrevNextAmount: 3,
            preloaderClass: 'loader'
        },
        pagination: {
            clickable: true
        },
        alaress: {
            autoplayDelay: 5000
        }
    };


    /**
     * Constructor.
     * A separate instance will exist on every jQuery
     * element upon which $().alaressSlider() is invoked.
     */
    function alaressSlider(element, options) {
        var alaressSlider = this;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);

        var mySwiper = new Swiper(element, alaressSlider.options);

        // Don't start autoplay until first image has loaded
        mySwiper.on('lazyImageReady', function() {
            if (!mySwiper.params.autoplay.enabled) { // TODO: this should also check the image that was loaded was the active one
                mySwiper.params.autoplay = {
                    delay: alaressSlider.options.alaress.autoplayDelay,
                    disableOnInteraction: false
                };
                mySwiper.autoplay.start();
            }
        });
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

})(jQuery, window, document, Swiper);
