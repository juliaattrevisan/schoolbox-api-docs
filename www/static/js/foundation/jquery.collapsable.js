/**
 * Make homepage components collapsable
 */
(function () {
    const DEFAULT_ICON_COLLAPSED = "icon-down-arrow";
    const DEFAULT_ICON_EXPANDED = "icon-up-arrow";
    const DEFAULT_SBX_ICON_COLLAPSED = "down-arrow";
    const DEFAULT_SBX_ICON_EXPANDED = "up-arrow";

    function getSbxIcon(toggle) {
        return $(toggle).find("sbx-icon").first();
    }

    function hasExpandedIcon(toggle, expandedClass, expandedIconName) {
        const sbxIcon = getSbxIcon(toggle);

        if (sbxIcon.length) {
            return sbxIcon.attr("name") === expandedIconName;
        }

        return $(toggle).hasClass(expandedClass);
    }

    function setCollapsedStateIcon(
        toggle,
        expanded,
        collapsedClass,
        expandedClass,
        collapsedIconName,
        expandedIconName,
    ) {
        const sbxIcon = getSbxIcon(toggle);

        if (sbxIcon.length) {
            sbxIcon.attr("name", expanded ? expandedIconName : collapsedIconName);
            return;
        }

        if (expanded) {
            $(toggle).removeClass(collapsedClass).addClass(expandedClass);
            return;
        }

        $(toggle).removeClass(expandedClass).addClass(collapsedClass);
    }

    /**
     * Compute dimensions dynamically
     *
     * @param options
     */
    $.fn.collapsable = function (options) {
        options = options || {};

        this.config = init(options, $(this));

        setupCollapsable.call(this);

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,
                container: "[data-component]",
                iconCollapsed: DEFAULT_ICON_COLLAPSED,
                iconExpanded: DEFAULT_ICON_EXPANDED,
                preventDefault: true, // whether event.preventDefault() should be called on the toggle's click event
                event: "click",
            };

            return $.extend(defaults, options);
        }

        /**
         * Set up collapsable toggle
         */
        function setupCollapsable() {
            var self = this;
            $(self.config.element).on(`${self.config.event}.collapsable`, function (event) {
                if (self.config.preventDefault === true) {
                    event.preventDefault();
                }

                var component,
                    target = $(this).data("collapser"),
                    simple = target === true || target.length === undefined,
                    icon = "";

                if (simple) {
                    // if the string "true" is used, or no value, target the container
                    component = $(this).closest(self.config.container).find('[data-collapsable="true"]');
                    icon = $(this).closest('[data-collapser="true"]');
                } else {
                    // otherwise, treat the value as the data-collapsable target value
                    component = $("[data-collapsable=" + target + "]");
                    icon = $(this);
                }

                $.fn.executeCollapsable.call(self, component, icon);
            });
        }
    };

    /**
     * Destroy a previously set up collapsable.
     */
    $.fn.destroyCollapsable = function (options) {
        // Mimic the element from the collapsable's init
        options = options || {};
        let config = $.extend({ element: $(this) }, options);
        $(config.element).off("click.collapsable");
    };

    /**
     * Directly execute collapsable on demand
     *
     * component is the element which expands/collapses and contains
     * the data vars "data-collapsable" and "data-collapse-state"
     *
     * icon is the element which activates the expanding/collapsing
     * and contains the data var "data-collapser"
     *
     * @param component
     * @param icon
     */
    $.fn.executeCollapsable = function (component, icon) {
        /* When it's collapsed by default, it just renders with .hide
         * It's actually uncollapsed, just hidden.
         *
         * This slides it up real fast so it can slide back down gracefully
         */
        if (component.attr("data-collapse-state") == "collapsed") {
            component
                .stop()
                .removeClass("hide")
                .slideUp(1)
                .slideDown({
                    start: function () {
                        component.trigger("expandStart.collapsable");
                    },
                    done: function () {
                        component.trigger("expandStop.collapsable");
                    },
                });
            component.attr("data-collapse-state", "uncollapsed");
        } else {
            component.attr("data-collapse-state", "collapsed");
            component.stop().slideUp({
                start: function () {
                    component.trigger("collapseStart.collapsable");
                },
                done: function () {
                    component.trigger("collapseStop.collapsable");
                },
            });
        }

        $.fn.executeIconToggle.call(this, icon);
    };

    /**
     * Explicitly and abruptly shut an accordion and reset its state and arrow
     * Ignore all the ux animations and stuff; just slam it shut
     *
     * @param component
     */
    $.fn.executeCollapsableSnapShut = function (component) {
        component.stop().slideUp(1).attr("data-collapse-state", "collapsed");

        let icon = $('[data-collapser="' + component.attr("data-collapsable") + '"]');

        // some places that aren't collapsable sometimes decide to call this, so fall back to the default icon if so
        const iconCollapsed = this.config?.iconCollapsed ?? DEFAULT_ICON_COLLAPSED;
        const iconExpanded = this.config?.iconExpanded ?? DEFAULT_ICON_EXPANDED;
        const sbxIconCollapsed = this.config?.sbxIconCollapsed ?? DEFAULT_SBX_ICON_COLLAPSED;
        const sbxIconExpanded = this.config?.sbxIconExpanded ?? DEFAULT_SBX_ICON_EXPANDED;

        setCollapsedStateIcon(icon, false, iconCollapsed, iconExpanded, sbxIconCollapsed, sbxIconExpanded);
    };

    /**
     * Toggle expand/collapse icon state
     *
     * @param icon
     */
    $.fn.executeIconToggle = function (icon) {
        // some places that aren't collapsable sometimes decide to call this, so fall back to the default icon if so
        const iconCollapsed = this.config?.iconCollapsed ?? DEFAULT_ICON_COLLAPSED;
        const iconExpanded = this.config?.iconExpanded ?? DEFAULT_ICON_EXPANDED;
        const sbxIconCollapsed = this.config?.sbxIconCollapsed ?? DEFAULT_SBX_ICON_COLLAPSED;
        const sbxIconExpanded = this.config?.sbxIconExpanded ?? DEFAULT_SBX_ICON_EXPANDED;

        setCollapsedStateIcon(
            icon,
            !hasExpandedIcon(icon, iconExpanded, sbxIconExpanded),
            iconCollapsed,
            iconExpanded,
            sbxIconCollapsed,
            sbxIconExpanded,
        );
    };
})(jQuery);
