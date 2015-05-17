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
                element: element
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

                var component = $(this).closest('.box').find('[data-collapsable=true]');

                if (component.attr('data-collapse-state') == 'collapsed') {
                    component.stop().slideDown();
                    component.attr('data-collapse-state', 'uncollapsed');

                    $(this).find('span').html('&ndash;');
                }
                else {
                    component.attr('data-collapse-state', 'collapsed');
                    component.stop().slideUp();

                    $(this).find('span').html('+');
                }
            });
        }
    }
}(jQuery));