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

        preview();

        /**
         * Initialise parameters
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {

                /* The element that activated preview
                 */
                element: element,

                /* The attribute to analyse
                 */
                attribute: 'href',

                /* Base url where preview is located
                 */
                baseUrl: '/preview'
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Preview
         *
         * Only apply to left-click events (though IE can't seem to
         * tell the difference)
         */
        function preview() {
            $(config.element).click(function(event) {
                if (event.which == 1) {
                    event.preventDefault();

                    $.magnificPopup.open({
                        items: {
                            src: "<div class='preview loader'>Loading data...</div>",
                            type: 'inline'
                        }
                    });

                    var url = config.baseUrl + '/' + encodeURIComponent($(this).attr(config.attribute));

                    $.get(url, function(response) {
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
                }
                else {
                    console.log(event.which + 'mort');
                }
            });
        }
    }
}(jQuery));