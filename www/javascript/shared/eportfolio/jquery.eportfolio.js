(function() {

    /**
     * Publish To Eportfolio
     *
     * Ajax a form which publishes an item to one's eportfolio
     * Show and hide a label indicating status
     */
    $.fn.eportfolioPublish = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupListeners();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {

                /* The eportfolio publish button
                 */
                element: element,

                /* For the backend
                 */
                action: 'publish',

                /* Processing endpoint
                 */
                url: '/eportfolio/showcase/modifyForm.php',

                /* Assessment id
                 */
                assessmentId: null,

                /* Student id
                 */
                userId: null,

                /* The label to hide/show when publishing
                 */
                label: '#publish-to-eportfolio-label'
            };

            return $.extend(defaults, options);
        }


        /**
         * Change visibility value for a particular Assessment
         * Ajaxes the new value silently
         */
        function setupListeners() {
            $.each(config.element, function(index, element) {

                $(element).on('click', function() {
                    var activator = $(this);

                    /* Only publish items that haven't been published before
                     */
                    if (activator.attr('data-publish-status') == 0) {

                        /* Provide confirmation dialog
                         */
                        activator.confirmDialog({
                            header: "Publish to ePortfolio Showcase",
                            text: 'Are you sure you want to publish this work to your ePortfolio showcase? This will make the work visible to everyone.',
                            confirmEvent: function() {

                                /* Update button status to prevent mass apm spam
                                 */
                                activator.attr('data-publish-status', 1);

                                /* Spawn toaster
                                 */
                                $(config.label).toastActivate({
                                    text: 'Sending to ePortfolio'
                                });

                                /* Send it to backend
                                 */
                                $.ajax({
                                    type: "POST",
                                    url: config.url,
                                    data: {
                                        action: config.action,
                                        assessmentId: config.assessmentId,
                                        userId: config.userId
                                    },
                                    success: function (data) {
                                        $(config.label).toastTimeout({
                                            text: 'Work published to ePortfolio'
                                        });

                                        /* Re-enable button
                                         */
                                        activator.attr('data-publish-status', 0);
                                    }
                                });
                            }
                        });
                    }
                });
            });
        }
    }
}(jQuery));
