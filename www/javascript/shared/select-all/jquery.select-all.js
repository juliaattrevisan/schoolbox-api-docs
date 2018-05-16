(function ($) {
    $.fn.selectAll = function (options) {
        options = options || {};

        var config = init(options, this);

        start();

        function init(options, element) {
            var defaults = {
                change: function () {},
                selector: '',
                checkboxes: '',
                childCheckboxes: '',
                childCheckboxGroups: ''
            };

            // This is necessary because we only want to select things
            // inside the element we're initialising the plugin on.
            var derived = {
                selector: element.find(options.selector),
                checkboxes: element.find(options.checkboxes),
                childCheckboxes: element.find(options.childCheckboxes),
                childCheckboxGroups: element.find(options.childCheckboxGroups)
            };

            var merged = $.extend(defaults, options, derived);

            return merged;
        }

        /** Select or deselect all checkboxes via the Select All checkbox. */
        function selectAllCheckboxes() {
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

        /** Check or uncheck the Select All box automatically,
        based on whether all checkboxes have been manually checked. */
        function autoSelectAllCheckboxes() {

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

        /** Determine and apply the correct icon (down or side arrow) on page load of a saved pastoral report
         * based on the persisted checked state of checkboxes */
        function setupDropdownIcons() {
            if (config.childCheckboxes) {
                config.checkboxes.each(function(index, checkbox) {
                    if ($(checkbox).is(":checked")) {
                        $(checkbox).siblings("label").find("i").removeClass("icon-click-view").addClass("icon-dropdown");
                    } else {
                        $(checkbox).siblings("label").find("i").removeClass("icon-dropdown").addClass("icon-click-view");
                    }
                });
            }
        }

        function start() {
            setupDropdownIcons();
            autoSelectAllCheckboxes();

            config.selector.change(function () {
                selectAllCheckboxes();

                config.change();
            });

            config.checkboxes.change(function () {
                autoSelectAllCheckboxes();
            });

            if (config.childCheckboxes) {
                config.childCheckboxes.change(function () {
                    autoSelectAllCheckboxes();
                });
            }
        }
    };
}(jQuery));