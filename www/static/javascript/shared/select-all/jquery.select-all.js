(function ($) {
    /**
     * Expected to be initiated on a container that contains the inter-related checkboxes
     *
     * @param {Object}   options                      Required
     * @param {string}   options.selector             Required   jQuery selector to identify the 'select-all' checkbox
     * @param {string}   options.checkboxes           Required   jQuery selector to identify the set of all relevant checkboxes
     * @var   {string}   options.childCheckboxes      Optional   jQuery selector to identify the child checkboxes
     * @var   {string}   options.childCheckboxGroups  Optional   jQuery selector to identify the child checkbox group containers
     * @var   {string}   options.observerParent       Optional   jQuery selector to identify the direct parent of elements in the DOM that will be added or removed via AJAX
     * @var   {function} options.change               Optional   callback to execute when the 'select-all' checkbox is toggled
     */
    $.fn.selectAll = function (options) {

        var config = {},
            $this  = this;

        validateConstructionOptions(options);

        function setup() {
            config = init(options, $this);
            start();
        }

        setup();

        // TODO this 'if' block is just a temp hack to get this plugin working with other plugins that disable/enable form inputs.
        //      Probably should expose the disconnect() method so other plugin can disable/enable the observation.
        if(config.observerParent) {
            // Reinitialise on DOM change
            var targetNode = config.observerParent.get(0);
            var observerOptions = {childList: true, attributes: true};
            var observer = new MutationObserver(setup);
            observer.observe(targetNode, observerOptions);
        }


        /**
         * Validate plugin construction args
         *
         * @param {object} options
         */
        function validateConstructionOptions(options) {
            var required = ['selector', 'checkboxes'],
                optional = ['childCheckboxes', 'childCheckboxGroups', 'observerParent', 'change'],
                all      = required.concat(optional),
                errors   = [];

            // Check that options is an object.
            if (options === null) {
                errors.push('options cannot be null');
            } else if (typeof options !== 'object') {
                errors.push("options must be an object. Found: '" + typeof options + "'");
            } else {
                // Check only valid args were specified
                for (var i in options) {
                    if (!options.hasOwnProperty(i)) {
                        continue;
                    }
                    if (-1 === all.indexOf(i)) {
                        errors.push("unrecognised option: '" + i + "'");
                    }
                }

                // check all required args were supplied
                for (var j in required) {
                    if (!options.hasOwnProperty(required[j])) {
                        errors.push("required option missing: '" + required[j] + "'");
                    }
                }
            }

            if (errors.length) {
                throw new Error('Invalid construction args: ' + errors.join('; '));
            }
        }

        /**
         * Builds and returns plugin config
         *
         * @param {object} options   Plugin construction options
         * @param {object} $element  jQuery object the plugin is attached to
         * @return {object}
         */
        function init(options, $element) {
            var defaults = {
                    childCheckboxes: '',
                    childCheckboxGroups: '',
                    observerParent: '',
                    change: function () {
                    }
                },
                derived = $.extend(defaults, options);

            // This is necessary because we only want to select things
            // inside the element we're initialising the plugin on.
            return {
                selector: $element.find(derived.selector),
                checkboxes: $element.find(derived.checkboxes).not(":disabled"),
                childCheckboxes: $element.find(derived.childCheckboxes).not(":disabled"),
                childCheckboxGroups: $element.find(derived.childCheckboxGroups),
                // observerParent: derived.observerParent ? $(derived.observerParent) : $element,
                observerParent: derived.observerParent ? $(derived.observerParent) : null, // TODO uncomment line above, delete this line. (see TODO above)
                change: derived.change
            };
        }

        /** Select or deselect all checkboxes via the Select All checkbox. */
        function selectAllCheckboxes() {
            if ( config.selector.is(':checked') ) {
                config.checkboxes.filter(':unchecked').prop('checked', true).trigger('change');
            } else {
                config.checkboxes.filter(':checked').prop('checked', false).trigger('change');
            }

            // If we have nested checkboxes, affect them as well
            if ( config.childCheckboxes ) {
                if ( config.selector.is(':checked') ) {
                    config.childCheckboxGroups.show();
                    config.childCheckboxes.filter(':unchecked').prop("checked", true).trigger('change');
                    config.checkboxes.siblings("label").find("i").removeClass("icon-click-view").addClass("icon-dropdown");
                } else {
                    config.childCheckboxGroups.hide();
                    config.childCheckboxes.filter(':checked').prop("checked", false).trigger('change');
                    config.checkboxes.siblings("label").find("i").removeClass("icon-dropdown").addClass("icon-click-view");
                }
            }
        }

        /** Check or uncheck the Select All box automatically,
        based on whether all checkboxes have been manually checked. */
        function autoSelectAllCheckboxes() {

            // If all parent checkboxes are checked...
            if ((config.checkboxes.length === config.checkboxes.filter(":checked").length) && config.checkboxes.length) {

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
