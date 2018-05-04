(function ($) {
    $.fn.selectAll = function (options) {
        options = options || {};

        var config = init(options, this);

        start();

        function init(options, element) {
            var defaults = {
                change: function () {},
                selectAllNested: function () {},
                autoSelectAllNested: function () {}
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
            if (config.checkboxes.length === config.checkboxes.filter(":checked").length) {
                config.selector.prop("checked", true);
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

            config.checkboxes.change(function () {
                autoSelectAll();
            });
            config.subCheckboxes.change(function () {
                config.autoSelectAllNested(config.selector, config.subCheckboxes);
            });
        }
    };
}(jQuery));