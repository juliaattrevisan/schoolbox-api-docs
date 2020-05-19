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

                /* Processing endpoint
                 */
                url: '/eportfolio/showcase/modifyForm.php',

                /* Student id
                 */
                userId: null,

                /* The label to hide/show when publishing
                 */
                label: '#publish-to-eportfolio-label',

                /* Text strings and backend action string
                 */
                publish: {
                    text: 'Are you sure you want to publish this work to your ePortfolio showcase? This will make the work visible to everyone.',
                    action: 'publish'
                },

                unpublish: {
                    text: 'Are you sure you want to remove this work from your ePortfolio showcase?',
                    action: 'unpublish'
                }
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
                    var assessmentId = activator.attr('data-publish-to-eportfolio');

                    /* Only publish items that haven't been published before
                     */
                    if (activator.attr('data-publish-status') == 0) {

                        /* Decide which text to show
                         */
                        var text;
                        var action;

                        if (activator.attr('data-publish-state') == 1) {
                            text = config.publish.text;
                            action = config.publish.action;
                        } else {
                            text = config.unpublish.text;
                            action = config.unpublish.action;
                        }

                        /* Provide confirmation dialog
                         */
                        activator.confirmDialog({
                            header: "Update ePortfolio Showcase",
                            text: text,
                            confirmEvent: function() {

                                /* Update button status to prevent mass apm spam
                                 */
                                activator.attr('data-publish-status', 1);

                                /* Spawn toaster
                                 */
                                $(config.label).toastActivate({
                                    text: 'Updating ePortfolio'
                                });

                                /* Send it to backend
                                 */
                                $.ajax({
                                    type: 'POST',
                                    url: config.url,
                                    data: {
                                        action: action,
                                        assessmentId: assessmentId,
                                        userId: config.userId
                                    },
                                    success: function (data) {

                                        /* Reload upon publish
                                         */
                                        location.reload();
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
