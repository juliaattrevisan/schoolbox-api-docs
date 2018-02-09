(function() {

    /**
     * Publish To Eportfolio
     *
     * Ajax a form which publishes an item to one's eportfolio
     * Show and hide a label indicating status
     */
    $.fn.eportfolio_publish = function(options) {
        options = options || {};

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
                label: '#publish-to-eportfolio-label'
            };

            var derived = {
            };

            return $.extend(defaults, derived, options);
        }

        /**
         * Change visibility value for a particular Due Work/Quiz
         * Ajaxes the new value silently
         */
        function publishToEportfolio() {

            /* If it's not already publishing, allow transaction
             * Otherwise just ignore the click
             *
             * This prevents mass apm spam
             */
            if ($(config.activator).attr('data-publish-status') == 0) {

                /* Indicate that we're currently publishing this
                 */
                $(config.activator).attr('data-publish-status', 1);

                $(config.label).toastActivate({
                    text: 'Sending to ePortfolio'
                });

                /* Ajax the Publish action
                 */
                $.ajax({
                    type: "POST",
                    url: config.element.attr('action'),
                    data: config.element.serialize(), // serializes the form's elements.
                    success: function (data) {
                        $(config.label).toastTimeout({
                            text: 'Work published to ePortfolio'
                        });

                        /* Re-enable button
                         */
                        $(config.activator).attr('data-publish-status', 0);
                    }
                });
            }

        }
    }
}(jQuery));