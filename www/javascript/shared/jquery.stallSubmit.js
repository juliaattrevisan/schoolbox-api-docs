/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
;(function ($, window, document, undefined) {

    var pluginName = 'stallSubmit';

    var defaults = {
        event: 'submit', // for Mage this needs to be click
    };


    function StallSubmit(element, options) {
        var staller = this;

        this.element   = element;
        this.options   = undefined;
        this.deferreds = [];

        setOptions.call(this, options);

        // register the listener
        $(this.element).on(this.options.event, function (e, shortCircuit) {

            if (shortCircuit === pluginName) {
                // allow the event to occur
                return true;
            }

            // there are unresolved deferreds: do not event yet
            e.preventDefault();
            e.stopPropagation();

            $.when.apply(window, staller.getDeferreds()).done(function () {
                // ok *now* you may event
                $(staller.element).trigger(staller.options.event, [ pluginName ]);
            });
        });
    }


    /**
     * Expects to be applied to the Mage object (but is not a prototype method so as to keep it private).
     *
     * @param {Object} opts
     */
    function setOptions(opts) {

        // if options are already defined, merge with that, otherwise defaults
        var merge = typeof this.options === 'undefined' ? defaults : this.options;

        // and apply the merged set
        this.options = $.extend(true, {}, merge, opts);
    }


    StallSubmit.prototype.push = function (deferred) {
        this.deferreds.push(deferred);
    };


    StallSubmit.prototype.getDeferreds = function () {
        return this.deferreds;
    };


    $.fn[pluginName] = function (options) {

        var plugin = 'plugin_' + pluginName;

        var type = typeof options;

        // the options given is a deferred
        if (type === 'object' && 'then' in options && $.isFunction(options.then)) {
            // attach this deferred to each instance given
            return this.each(function () {
                if (!$.data(this, plugin)) {
                    // initialise if not already
                    $.data(this, plugin, new StallSubmit(this, options));
                }

                $.data(this, plugin).push(options);
            });
        }

        if (type === 'object' || type === 'undefined') {
            // if this is configuration
            return this.each(function () {
                if (!$.data(this, plugin)) {
                    // initialise if not already
                    $.data(this, plugin, new StallSubmit(this, options));

                } else {
                    // just update existing config
                    setOptions.call($.data(this, plugin), options);
                }
            });

        } else if (type === 'string') {
            // if this is a function request
            var rtn = [];

            this.each(function () {
                var staller = $(this).data(plugin);

                if (typeof staller === 'undefined') {
                    throw "StallSubmit not initialised";
                }

                // attempt a method call
                var method = options;

                if (method in staller) {
                    rtn.push(staller[method].call(staller));
                }
            });

            return $($.map(rtn, function (el) { return $.makeArray(el) } ));
        }

    };

})(jQuery, window, document);