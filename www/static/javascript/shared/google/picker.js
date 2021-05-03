
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

                $(config.modalId).foundation('reveal', 'open');
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

                /* As painful as this is...
                 * https://stackoverflow.com/questions/45809722/how-do-i-execute-an-action-when-an-attribute-value-changes-using-jquery-or-javas
                 *
                 * After the picker dom gets rammed in, watch for state changes
                 * so we can shut our modal when we shut the google modal
                 */
                let MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver

                let observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === "attributes") {
                            if (mutation.target.getAttribute('aria-hidden') !== "true") {
                                $(config.modalId).foundation('reveal', 'close');
                            }
                        }
                    });
                });

                observer.observe(document.querySelector('.picker-dialog-bg'), {
                    attributes: true, //configure it to listen to attribute changes
                    attributeFilter: ['aria-hidden'] // filter your attributes
                });
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

            /* Shut google modal when we shut our modal
             * https://stackoverflow.com/questions/13745505/how-to-use-zurb-foundation-reveal-with-open-opened-close-closed-callback-func
             */
            $(document).on('close.fndtn.reveal', config.modalId, function() {
                picker.setVisible(false);
            });
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