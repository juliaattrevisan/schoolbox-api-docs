/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
(function ($, window, document, undefined) {
    var pluginName = "rubricEditable";

    var defaults = {
        hideHorizontalClass: "hide-h",
        hideVerticalClass: "hide-v",
        inputSelector: "input[type=text],input[type=tel],textarea",
        levelLimit: 0,
        url: "",
        swap: function (move, first, second) {
            first[move](second);
        },
        afterApplyResultSet: $.noop,
        rubricId: null,
        formField: "rb",
        localisedGroupName: "Capability",
        localisedCriterionName: "Indicator",
    };

    function RubricEditable(element, options) {
        this.element = element;
        this.options = $.extend(true, {}, defaults, options);

        // ensure an ID is set
        if (this.options.rubricId === null) {
            this.options.rubricId = this.element.id;
        }
    }

    /**
     * Replace the existing levels and descriptors with the array given
     *
     * @param {Array} options
     */
    RubricEditable.prototype.applyResultSet = function (options) {
        // TODO: mimic in vue
        var rubricEditable = this;

        // delete all but the first level
        var i = 0;
        while (i++ < 100) {
            var levelCount = this.getLevelCount();
            if (levelCount <= 1) {
                break;
            }
            this.deleteLevel(levelCount - 1);
        }

        // Add each level.
        // After adding the first level in this list - delete the first
        // (now unneeded) level, so that there's always room for adding
        // the maximum number of levels.
        var isFirstDeleted = false;
        $.each(options, function (levelValue, levelName) {
            rubricEditable.addLevel(levelName, levelValue);
            if (!isFirstDeleted) {
                isFirstDeleted = true;
                rubricEditable.deleteLevel(0);
            }
        });

        this.options.afterApplyResultSet.call(this);
    };

    $.fn[pluginName] = function (options) {
        var plugin = "plugin_" + pluginName;

        if (typeof options === "object" || typeof options === "undefined") {
            // if this is configuration
            return this.each(function () {
                if (!$.data(this, plugin)) {
                    // initialise if not already
                    $.data(this, plugin, new RubricEditable(this, options));
                } else {
                    // just update existing config
                    setOptions.call($.data(this, plugin), options);
                }
            });
        } else if (typeof options === "string") {
            // if this is a function request
            var rtn = [];

            this.each(function () {
                var rbe = $(this).data(plugin);

                if (typeof rbe === "undefined") {
                    throw "RubricEditable not initialised";
                }

                // return the thing I'm pointing at
                if (options === "next" || options === "target") {
                    rtn.push($('[data-mage="' + $(rbe.element).data("mage-next") + '"]'));
                }

                // attempt a method call
                if (options in rbe) {
                    rtn.push(rbe[options].call(rbe));
                }
            });

            return $(
                $.map(rtn, function (el) {
                    return $.makeArray(el);
                }),
            );
        }
    };

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
})(jQuery, window, document);
