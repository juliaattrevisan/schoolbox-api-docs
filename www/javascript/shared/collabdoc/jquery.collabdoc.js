(function($) {
    $.fn.collabDoc = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupDimensions();
        setupDelete();

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
                width: '100%',
                deleteButton: $('[data-deleteButton="1"]')
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Set up iframe dimensions upon init and resize
         */
        function setupDimensions() {

            /* Initial dimensions
             */
            $(config.element).css({
                width: config.width,
                height: $(window).height()*0.66
            });

            /* Resized dimensions
             */
            $(window).resize(function() {
                $(config.element).css({
                    width: config.width,
                    height: $(window).height()*0.66
                });
            });
        }

        /**
         * Custom behaviour when clicking delete
         */
        function setupDelete() {
            config.deleteButton.click(function(event) {
                event.preventDefault();

                if (confirm('Are you sure you want to delete this Collab Doc?')) {
                    window.location.href = $(this).attr('href');
                }
            });
        }
    }
}(jQuery));

