(function ($) {
    $.fn.selectAll = function (options) {
        options = options || {};

        var config = init(options, this);

        start();

        function init(options, element) {
            var defaults = {
                change: function () {},
                selectAllNested: function () {},
            };

            var derived = {
                selector: element.find("[data-" + options.key + "='true']"),
                checkboxes: element.find("input[name='" + options.checkboxName + "']"),
                subCheckboxes: element.find("input[name='" + options.checkboxName + "']")
            };

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        // Select or deselect all checkboxes via the Select All checkbox.
        function selectAll() {
            if (config.selector.is(':checked')) {
                config.checkboxes.prop('checked', true);
            } else {
                config.checkboxes.prop('checked', false);
            }

            config.selectAllNested(config.selector, config.subCheckboxes);
        }

        // Check or uncheck the Select All box automatically,
        // based on whether all checkboxes have been manually checked.
        function autoSelectAll() {

            // If all parent checkboxes are checked...
            if (config.checkboxes.length === config.checkboxes.filter(":checked").length) {

                // If we have nested checkboxes, make sure they're all checked as well
                if (config.subCheckboxes) {
                    if (config.subCheckboxes.length === config.subCheckboxes.filter(":checked").length) {
                        config.selector.prop("checked", true);
                    } else {
                        config.selector.prop("checked", false);
                    }
                } else {
                    config.selector.prop("checked", true);
                }

            } else {
                config.selector.prop("checked", false);
            }

        }

        function start() {
            autoSelectAll();

            config.selector.change(function () {
                selectAll();

                config.change();
            });

            config.checkboxes.change(function() {
                autoSelectAll();
            });

            if (config.subCheckboxes) {
                config.subCheckboxes.change(function() {
                    autoSelectAll();
                });
            }
        }
    };
}(jQuery));