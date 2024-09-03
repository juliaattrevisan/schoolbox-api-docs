/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
(function ($, window, document, undefined) {
    var pluginName = "sequencer";

    var defaults = {
        verticalHook: "sequencer-v",
        horizontalHook: "sequencer-h",
        groupHook: "sequencer-group",
        upButton: "[data-sequencer-up]",
        downButton: "[data-sequencer-down]",
        leftButton: "[data-sequencer-left]",
        rightButton: "[data-sequencer-right]",
        swap: function (axis, first, second) {
            console.warn("No swap functionality implemented");
        },
    };

    function Sequencer(element, options) {
        var self = this;

        this.element = element;
        this.options = $.extend(true, {}, defaults, options);

        this.allButtons = [
            this.options.upButton,
            this.options.downButton,
            this.options.leftButton,
            this.options.rightButton,
        ];
        this.verticalButtons = [this.options.upButton, this.options.downButton];
        this.forwardButtons = [this.options.downButton, this.options.rightButton];
        this.backButtons = [this.options.upButton, this.options.leftButton];
        this.sequenceHooks = ["data-" + this.options.verticalHook, "data-" + this.options.horizontalHook];

        this.refresh();

        var opt = this.options;

        $(element).on("click", this.allButtons.join(","), function (e) {
            // if a forward button is clicked
            e.preventDefault();

            // figure out what we actually want to do...
            var clicked = $(this);
            var sequencer = clicked.closest("[" + self.sequenceHooks.join("],[") + "]");
            var axis = clicked.filter(self.verticalButtons.join(",")).length ? "vertical" : "horizontal";
            var dir = clicked.filter(self.forwardButtons.join(",")).length ? 1 : -1;
            var sequenceId = sequencer.data(axis == "vertical" ? opt.verticalHook : opt.horizontalHook);
            var search = "[data-" + opt.groupHook + '="' + sequenceId + '"]';
            var elements = $(self.element).find(search);
            var current = sequencer.closest(search);
            var currIndex = elements.index(current);

            // apply swap callback
            opt.swap.call(self.element, axis, elements.eq(currIndex), elements.eq(currIndex + dir));

            // refresh just this sequence id
            self.refresh(sequenceId);
        });
    }

    Sequencer.prototype.refresh = function (sequenceId) {
        var opt = this.options;
        var elem = $(this.element);
        var self = this;

        // work in both directions
        $.each([opt.verticalHook, opt.horizontalHook], function (i, hook) {
            var sequenceIds;

            if (typeof sequenceId !== "undefined") {
                sequenceIds = [sequenceId];
            } else {
                sequenceIds = $.unique(
                    Array.prototype.slice.call(
                        elem.find("[data-" + hook + "]").map(function () {
                            return $(this).data(hook);
                        }),
                    ),
                );
            }

            $.each(sequenceIds, function (j, sequenceId) {
                var sequencers = elem.find("[data-" + hook + '="' + sequenceId + '"]');

                // re-enable all sequencers in this series
                sequencers.children().removeAttr("disabled");

                sequencers.eq(0).children(self.backButtons.join(",")).attr("disabled", "disabled");
                sequencers.eq(-1).children(self.forwardButtons.join(",")).attr("disabled", "disabled");
            });
        });
    };

    $.fn[pluginName] = function (options) {
        if (typeof options === "string") {
            // umm... so this works... *somehow* (convert object to array)
            var args = Array.prototype.slice.call(arguments);

            return this.each(function () {
                if ($.data(this, "plugin_" + pluginName)) {
                    var inst = $.data(this, "plugin_" + pluginName);

                    if ($.isFunction(inst[options])) {
                        inst[options].apply(inst, args.splice(1));
                    }
                }
            });
        }

        return this.each(function () {
            if (!$.data(this, "plugin_" + pluginName)) {
                $.data(this, "plugin_" + pluginName, new Sequencer(this, options));
            }
        });
    };
})(jQuery, window, document);
