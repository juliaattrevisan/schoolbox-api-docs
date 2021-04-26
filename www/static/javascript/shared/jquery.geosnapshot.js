(function() {

    /**
     * Geosnapshots
     *
     * @param options
     */
    $.fn.geosnapshot = function(options) {
        options = options || {};

        let config = init(options, $(this));

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            let defaults = {
                element: element,
                studentPhotosUrl: '/geosnapshot/user'
            };

            return $.extend(defaults, options);
        }

        return {

            /**
             * Load a gallery for a particular person
             *
             * @param userId int
             */
            'requestGallery': function(userId) {
                let toast = $('[data-toast]');

                toast.toastActivate({text: 'Retrieving photos...'});

                /* No user id? No trabajo
                 */
                if (!userId) {
                    toast.toastTimeout({
                        text: 'Something went wrong. Please try again.',
                        css: 'alert'
                    });

                    return;
                }

                /* Manufacture a url
                 */
                let url = config.studentPhotosUrl + '/' + userId;

                /* Go there and get something
                 */
                $.get(url, function(responseText) {
                    window.location.href = responseText.url;
                }).fail(function() {
                    toast.toastTimeout({
                        text: 'Something went wrong. Please try again.dd',
                        css: 'alert'
                    });
                });
            }
        };
    }
}(jQuery));