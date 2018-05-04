(function ($) {
    $.fn.selectAll = function (options) {
        options = options || {};

        var config = init(options, this);

        start();

        function init(options, element) {
            var defaults = {
                change: function () {},
                customSelectAll: function () {},
                customAutoSelectAll: function () {}
            };

            var derived = {
                selector: element.find("[data-" + options.key + "='true']"),
                checkboxes: element.find("input[name='" + options.checkboxName + "']")
            };

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        // Select or deselect all checkboxes via the Select All checkbox.
        function selectAll(selector) {
            if ($(selector).is(':checked')) {
                config.checkboxes.prop('checked', true);
            } else {
                config.checkboxes.prop('checked', false);
            }
        }

        // Check or uncheck the Select All box automatically,
        // based on whether all checkboxes have been manually checked.
        function autoSelectAll() {
            config.checkboxes.change(function() {
                if (config.checkboxes.length === config.checkboxes.filter(":checked").length) {
                    config.selector.prop("checked", true);
                } else {
                    config.selector.prop("checked", false);
                }
            });
        }

        function start() {
            selectAll(config.selector);
            // config.customSelectAll();

            config.selector.change(function () {
                selectAll(this);
                // config.customSelectAll();

                config.change();
            });

            autoSelectAll();
            // config.customAutoSelectAll();
        }

    };
}(jQuery));