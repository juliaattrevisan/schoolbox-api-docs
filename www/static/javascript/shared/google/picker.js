
(function() {

    /**
     * Google Picker
     *
     * Pick files from Google, save their links locally, share
     * it on a homepage
     *
     * @param options
     */
    $.fn.googlePicker = function(options) {
        options = options || {};

        let config = init(options, $(this));

        /* Only proceed if both parts set
         */
        if (config.clientId.length && config.postGoogleFileUrl.length) {
            setupListeners();
        }


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
                modalId: '#gfile',
                clientId: '',
                postGoogleFileUrl: '',
                scope: 'https://www.googleapis.com/auth/drive.file'
            };

            return $.extend(defaults, options);
        }


        /**
         * Activate links
         */
        function setupListeners() {
            $(config.element).on('click', function() {
                loadGoogleApi(['auth2', 'picker'], loadPicker);

                $(config.modal).foundation('reveal', 'open');
            });
        }


        /**
         * Ask google to setup picker
         */
        function loadPicker() {
            window.gapi.auth2.authorize({
                'client_id': config.clientId,
                'scope': config.scope
            }, handleAuthResult);
        }


        /**
         * Respond to google
         */
        function handleAuthResult(authResult) {
            if (authResult && !authResult.error) {
                createPicker(authResult.access_token);
            }
        }


        // Create and render a Picker object for searching images.
        // Visit here to find all the view ids:
        //   https://developers.google.com/picker/docs/reference#view-id
        function createPicker(oauthToken) {
            let picker = new google.picker.PickerBuilder()
                .enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
                .setOAuthToken(oauthToken)

                /* This is documented in the API but it's currently kind of broken
                 *
                 * You can't do what their example tells you...DOCS encompasses all the types
                 * and you can choose them from the search bar/dropdown in the modal, regardless
                 * of any other addView() commands here
                 */
                .addViewGroup(
                    new google.picker.ViewGroup(google.picker.ViewId.DOCS)
                )
                .setCallback(pickerCallback)
                .build();
            picker.setVisible(true);
        }


        /**
         * Asynchronously execute this whenever you pick a file in the modal
         *
         * @param data
         */
        function pickerCallback(data) {
            if (data.action === google.picker.Action.PICKED) {
                $.ajax({
                    type: 'POST',
                    url: config.postGoogleFileUrl,
                    data: {docs: data.docs},
                    success: function (data) {
                        window.location.reload();
                    }
                });
            }
        }
    }
}(jQuery));