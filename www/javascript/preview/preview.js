(function() {

    /**
     * Constructor
     *
     * @param options
     */
    $.fn.preview = function(options) {
        options = options || {}

        /* Store the config in this instance so private methods
         * can access it
         */
        var config = init(options, this);

        $(config.element).click(function(event) {
            event.preventDefault();

            $.magnificPopup.open({
                items: {
                    src: "<div class='preview loader'>Loading data...</div>",
                    type: 'inline'
                }
            });

            $.get($(this).attr('href'), function(response) {
                $.magnificPopup.close();

                if (response.type == 'redirect') {
                    window.location.href = response.fragment;
                }
                else {
                    $.magnificPopup.open({
                        items: {
                            src: "<div class='preview fragment'>" + response.fragment + "</div>",
                            type: 'inline'
                        }
                    });
                }
            });
        });

        /**
         * Initialise parameters
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
    }
}(jQuery));