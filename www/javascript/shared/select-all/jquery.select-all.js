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
                childCheckboxGroups: '',
                parent: element.selector
            };

            var merged = $.extend(defaults, options);

            return merged;
        }

        /** Select or deselect all checkboxes via the Select All checkbox. */
        function selectAllCheckboxes() {
            if ( $(config.parent).find(config.selector).is(':checked') ) {
                $(config.parent).find(config.checkboxes).prop('checked', true);
            } else {
                $(config.parent).find(config.checkboxes).prop('checked', false);
            }

            // If we have nested checkboxes, affect them as well
            if ( $(config.parent).find(config.childCheckboxes) ) {
                if ( $(config.parent).find(config.selector).is(':checked') ) {
                    $(config.parent).find(config.childCheckboxGroups).show();
                    $(config.parent).find(config.childCheckboxes).prop("checked", true);
                    $(config.parent).find(config.checkboxes).siblings("label").find("i").removeClass("icon-click-view").addClass("icon-dropdown");
                } else {
                    $(config.parent).find(config.childCheckboxGroups).hide();
                    $(config.parent).find(config.childCheckboxes).prop("checked", false);
                    $(config.parent).find(config.checkboxes).siblings("label").find("i").removeClass("icon-dropdown").addClass("icon-click-view");
                }
            }
        }

        /** Check or uncheck the Select All box automatically,
        based on whether all checkboxes have been manually checked. */
        function autoSelectAllCheckboxes() {

            // If all parent checkboxes are checked...
            if ($(config.parent).find(config.checkboxes).length === $(config.parent).find(config.checkboxes).filter(":checked").length) {

                // If we have nested checkboxes, make sure they're all checked as well
                if ($(config.parent).find(config.childCheckboxes)) {
                    if ($(config.parent).find(config.childCheckboxes).length === $(config.parent).find(config.childCheckboxes).filter(":checked").length) {
                        $(config.parent).find(config.selector).prop("checked", true);
                    } else {
                        $(config.parent).find(config.selector).prop("checked", false);
                    }
                } else {
                    $(config.parent).find(config.selector).prop("checked", true);
                }

            } else {
                $(config.parent).find(config.selector).prop("checked", false);
            }

        }

        /** Determine and apply the correct icon (down or side arrow) on page load of a saved pastoral report
         * based on the persisted checked state of checkboxes */
        function setupDropdownIcons() {
            if ($(config.parent).find(config.childCheckboxes)) {
                $(config.parent).find(config.checkboxes).each(function(index, checkbox) {
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

            $(config.parent).find(config.selector).change(function () {
                selectAllCheckboxes();

                config.change();
            });

            $(config.parent).find(config.checkboxes).change(function () {
                autoSelectAllCheckboxes();
            });

            if ($(config.parent).find(config.childCheckboxes)) {
                $(config.parent).find(config.childCheckboxes).change(function () {
                    autoSelectAllCheckboxes();
                });
            }
        }
    };
}(jQuery));