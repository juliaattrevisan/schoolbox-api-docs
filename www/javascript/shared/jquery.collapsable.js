/**
 * Make homepage components collapsable
 */
(function() {

    /**
     * Compute dimensions dynamically
     *
     * @param options
     */
    $.fn.collapsable = function(options) {
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
                container: '[data-component]',
                iconExpand: 'icon-down-arrow',
                iconCollapse: 'icon-up-arrow'
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Set up collapsable toggle
         */
        function setupCollapsable() {
            var self = this;
            $(self.config.element).click(function(event) {
                event.preventDefault();

                var component,
                    target = $(this).data('collapser'),
                    simple = target === true || target.length === undefined;

                if (simple) {
                    // if the string "true" is used, or no value, target the container
                    component = $(this).closest(config.container).find('[data-collapsable=true]');
                    icon      = $(this).closest('[data-collapser="true"]');
                } else {
                    // otherwise, treat the value as the data-collapsable target value
                    component = $('[data-collapsable=' + target + ']');
                    icon      = $(this);
                }

                $.fn.executeCollapsable.call(self, component, icon);
            });
        }
    }

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
    $.fn.executeCollapsable = function(component, icon) {

        /* When it's collapsed by default, it just renders with .hide
         * It's actually uncollapsed, just hidden.
         *
         * This slides it up real fast so it can slide back down gracefully
         */
        if (component.attr('data-collapse-state') == 'collapsed') {
            component.stop().removeClass('hide').slideUp(1).slideDown();
            component.attr('data-collapse-state', 'uncollapsed');
        }
        else {
            component.attr('data-collapse-state', 'collapsed');
            component.stop().slideUp();
        }

        $.fn.executeIconToggle.call(this, icon);
    }

    /**
     * Toggle expand/collapse icon state
     *
     * @param icon
     */
    $.fn.executeIconToggle = function(icon) {
        var classes = this.config ? [ this.config.iconExpand, this.config.iconCollapse ] : [ 'icon-down-arrow', 'icon-up-arrow' ];
        icon.toggleClass(classes.join(' '));
    }
}(jQuery));
