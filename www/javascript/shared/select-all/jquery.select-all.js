(function ($) {
    $.fn.selectAll = function (options) {
        options = options || {};

        var config = init(options, this);

        start();

        function init(options, element) {
            var defaults = {
                change: function () {},
                autoSelectAll: function () {
                    // TODO this filter seems to be tallying up ALL checked checkboxes on the page, not just the ones in config.checkboxes...how
                    if (config.checkboxes.filter(":checked").length === config.checkboxes.length) {
                        config.selector.prop("checked", true);
                    } else {
                        config.selector.prop("checked", false);
                    }
                }
            };

            var derived = {
                selector: element.find("[data-" + options.key + "='true']"),
                checkboxes: element.find("input[name='" + options.checkboxName + "']")
            };

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        function start() {
            config.autoSelectAll();

            config.selector.change(function () {
                if ($(this).is(':checked')) {
                    config.checkboxes.prop('checked', true);
                } else {
                    config.checkboxes.prop('checked', false);
                }

                config.change();
            });

            config.checkboxes.change(function () {
                config.autoSelectAll();
            });
        }
    };
}(jQuery));