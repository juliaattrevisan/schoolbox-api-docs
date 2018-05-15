(function ($) {
    $.fn.selectAll = function (options) {
        options = options || {};

        var config = init(options, this);

        start();

        function init(options, element) {
            var defaults = {
                change: function () {},
                selectAllNested: function () {},
                childCheckboxGroups: {}
            };

            var derived = {
                selector: element.find("[data-" + options.key + "='true']"),
                checkboxes: element.find("input[name='" + options.checkboxName + "']"),
                childCheckboxes: element.find("input[name='" + options.checkboxName + "']")
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

            // If we have nested checkboxes, affect them as well
            if (config.childCheckboxes) {
                if (config.selector.is(':checked')) {
                    config.childCheckboxGroups.show();
                    config.childCheckboxes.prop("checked", true);
                    config.checkboxes.siblings("label").find("i").removeClass("icon-click-view").addClass("icon-dropdown");
                } else {
                    config.childCheckboxGroups.hide();
                    config.childCheckboxes.prop("checked", false);
                    config.checkboxes.siblings("label").find("i").removeClass("icon-dropdown").addClass("icon-click-view");
                }
            }
        }

        // Check or uncheck the Select All box automatically,
        // based on whether all checkboxes have been manually checked.
        function autoSelectAll() {

            // If all parent checkboxes are checked...
            if (config.checkboxes.length === config.checkboxes.filter(":checked").length) {

                // If we have nested checkboxes, make sure they're all checked as well
                if (config.childCheckboxes) {
                    if (config.childCheckboxes.length === config.childCheckboxes.filter(":checked").length) {
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
                config.selectAllNested();

                config.change();
            });

            config.checkboxes.change(function() {
                autoSelectAll();
            });

            if (config.childCheckboxes) {
                config.childCheckboxes.change(function() {
                    autoSelectAll();
                });
            }
        }
    };
}(jQuery));