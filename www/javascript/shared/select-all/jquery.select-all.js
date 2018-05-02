(function ($) {
    $.fn.selectAll = function (options) {
        options = options || {};

        var config = init(options, this);

        start();

        function init(options, element) {
            var defaults = {
                change: function () {}
            };

            var derived = {
                selector: element.find("[data-" + options.key + "='true']"),
                checkboxes: element.find("input[name='" + options.checkboxName + "']")
            };

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        function start() {
            autoSelectAll(config);

            config.selector.change(function () {
                if ($(this).is(':checked')) {
                    config.checkboxes.prop('checked', true);
                } else {
                    config.checkboxes.prop('checked', false);
                }

                config.change();
            });

            config.checkboxes.change(function () {
                autoSelectAll(config);
            });
        }

        function autoSelectAll(config) {
            console.log("Number selected: " + $(":checked").length);
            console.log("Number total: " + config.checkboxes.length);
            if (config.checkboxes.filter(":checked").length === config.checkboxes.length) {
                config.selector.prop("checked", true);
            } else {
                config.selector.prop("checked", false);
            }
        }
    };
}(jQuery));