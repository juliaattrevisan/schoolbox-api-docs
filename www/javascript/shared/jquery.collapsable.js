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

        var config = init(options, $(this));

        setupCollapsable();

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
                container: '[data-component]'
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
            $(config.element).click(function(event) {
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

                icon.toggleClass('icon-collapse icon-add');
            });
        }
    }
}(jQuery));