(function ($) {
    $.fn.selectAll = function (options) {
        options = options || {};

        var config = init(options, this);

        start();

        function init(options, element) {
            var defaults = {
                change: function () {},
                customSelectAll: function () {}
            };

            var derived = {
                selector: element.find("[data-" + options.key + "='true']"),
                checkboxes: element.find("input[name='" + options.checkboxName + "']")
            };

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        function start() {
            config.customSelectAll();

            config.selector.change(function () {
                if ($(this).is(':checked')) {
                    config.checkboxes.prop('checked', true);
                } else {
                    config.checkboxes.prop('checked', false);
                }

                config.change();
                config.customSelectAll();
            });
        }
    };
}(jQuery));