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
                url: ''
            };

            let derived = {
            };

            return $.extend(defaults, derived, options);
        }

        return {

            /**
             * Load a gallery for a particular person
             */
            'requestGallery': function() {
                let toast = $('#toast');

                toast.toastActivate({text: 'Retrieving photos...'});

                $.get(config.url, function(responseText) {

                    /* Temporarily ram this in
                     */
                    $('#component24').html('<iframe src="'+responseText.url+'" style="height:500px"></iframe>');

                    toast.toastTimeout({
                        text: 'Loaded.',
                    });
                }).fail(function() {
                    toast.toastTimeout({
                        text: 'Something went wrong. Please try again.',
                        css: 'alert'
                    });
                });
            }
        };
    }
}(jQuery));