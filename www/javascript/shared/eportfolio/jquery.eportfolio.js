(function() {

    /**
     * Publish To Eportfolio
     *
     * Ajax a form which publishes an item to one's eportfolio
     * Show and hide a label indicating status
     */
    $.fn.eportfolio_publish = function(options) {
        options = options || {}

        var config = init(options, $(this));

        publishToEportfolio();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {

                /* The eportfolio publish form
                 */
                element: element,

                /* The button which activates the Publishing
                 */
                activator: '#publish-to-eportfolio',

                /* The label to hide/show when publishing
                 */
                label: '#publish-to-eportfolio-status'
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Change visibility value for a particular Due Work/Quiz
         * Ajaxes the new value silently
         */
        function publishToEportfolio() {

            /* Event listener
             */
            $(config.activator).click(function(event) {
                event.preventDefault();

                $(config.label).labelActivate({
                    text: 'Preparing Showcase'
                });

                /* Ajax the Publish action
                 */
                $.ajax({
                    type: "POST",
                    url: config.element.attr('action'),
                    data: config.element.serialize(), // serializes the form's elements.
                    success: function(data) {
                        $(config.label).labelTimeout({
                            text: 'Showcase Updated'
                        });
                    }
                });
            });
        }
    }
}(jQuery));