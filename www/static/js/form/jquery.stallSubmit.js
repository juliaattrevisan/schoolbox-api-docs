/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
(function ($, window, document, undefined) {
    var pluginName = "stallSubmit";

    var defaults = {
        event: "submit", // for Mage this needs to be click
    };

    function StallSubmit(element, options) {
        var staller = this;

        this.element = element;
        this.options = undefined;
        this.deferreds = [];

        setOptions.call(this, options);

        // register the listener
        $(this.element).on(this.options.event, function (e, shortCircuit) {
            if (shortCircuit === pluginName) {
                // allow the event to occur
                return true;
            }

            // there are unresolved deferreds: do not event yet

            if (e && e.preventDefault) {
                e.preventDefault();
                e.stopPropagation();
            } else {
                return false;
            }

            $.when.apply(window, staller.getDeferreds()).done(function () {
                // ok *now* you may event
                $(staller.element).trigger(staller.options.event, [pluginName]);
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
        var merge = typeof this.options === "undefined" ? defaults : this.options;

        // and apply the merged set
        this.options = $.extend(true, {}, merge, opts);
    }

    /**
     * Push a deferred onto our stack
     *
     * @param {jQuery.Deferred} deferred
     */
    StallSubmit.prototype.push = function (deferred) {
        // because jQuery, if a Deferred goes to 'rejected' it will never resolve inside $.when()
        // therefore we have to wrap it in a shim... -_-
        var resolver = $.Deferred();

        // add a handler to the original Deferred that will *always* resolve the shim
        deferred.always(function () {
            resolver.resolve();
        });

        // put the shim on the stack rather than the original
        this.deferreds.push(resolver);
    };

    /**
     * Return the list of deferred items stalling this element
     *
     * @returns {Array}
     */
    StallSubmit.prototype.getDeferreds = function () {
        return this.deferreds;
    };

    $.fn[pluginName] = function (options) {
        var plugin = "plugin_" + pluginName;

        var type = typeof options;

        // the options given is a deferred
        if (type === "object" && "then" in options && $.isFunction(options.then)) {
            // attach this deferred to each instance given
            return this.each(function () {
                if (!$.data(this, plugin)) {
                    // initialise if not already
                    $.data(this, plugin, new StallSubmit(this, options));
                }

                $.data(this, plugin).push(options);
            });
        }

        if (type === "object" || type === "undefined") {
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
        } else if (type === "string") {
            // if this is a function request
            var rtn = [];

            this.each(function () {
                var staller = $(this).data(plugin);

                if (typeof staller === "undefined") {
                    throw "StallSubmit not initialised";
                }

                // attempt a method call
                var method = options;

                if (method in staller) {
                    rtn.push(staller[method].call(staller));
                }
            });

            return $(
                $.map(rtn, function (el) {
                    return $.makeArray(el);
                }),
            );
        }
    };
})(jQuery, window, document);
