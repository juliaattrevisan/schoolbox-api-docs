(function () {

    /**
     * Google Picker
     *
     * Pick files from Google, save their links locally, share
     * it on a homepage
     * TODO: we may want to merge this with gpick at some point
     *
     * @param options
     */
    $.fn.googlePicker = function (options) {
        options = options || {};

        let config = init(options, $(this));

        /* Only proceed if both parts set
         */
        if (config.clientId.length && config.modules) {
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

                /* The link which activates the google picker
                 */
                element: element,

                /* Our modal. The google modal appears on top of this
                 */
                modalId: '#gfile',

                /* Client id si required, as is postGoogleFileUrl
                 */
                clientId: '',
                postGoogleFileUrl: '',

                /* Callback
                 */
                onSelect: function () {
                },
                onAuth: function (e) {
                    $(config.modalId).foundation('reveal', 'open');
                    createPicker(e);
                },
                /* A google setting
                 */
                scope: 'https://www.googleapis.com/auth/drive',
                /* True to do auth only...
                 */
                modules: ['auth2', 'picker']
            };
            $('#gfile-google-ids').val('');
            return $.extend(defaults, options);
        }


        /**
         * Activate links
         */
        function setupListeners() {
            $(config.element).on('click', function () {
                loadGoogleApi(config.modules, authorize);
            });
        }


        /**
         * Ask google to setup picker
         * List of scopes: https://developers.google.com/identity/protocols/oauth2/scopes
         */
        function authorize() {
            gapi.auth2.init({
                'client_id': config.clientId,
                'scope': config.scope
            }).then(function () {
                let gAuth = gapi.auth2.getAuthInstance();
                let gUser = gAuth.currentUser.get();
                if (gUser.isSignedIn()) {
                    handleAuthResult(gUser.getAuthResponse());
                } else {
                    gAuth.signIn({'prompt': 'select_account'}).then(
                        function () {
                            handleAuthResult(gUser.getAuthResponse());
                        }
                    );
                }
            });
        }


        /**
         * Respond to google when you get past the authentication request
         * Spawn our modal and detect when we close the google modal; react accordingly
         */
        function handleAuthResult(authResult) {
            if (authResult && !authResult.error) {
                $('#gfile-access-token').val(authResult.access_token);
                $('#gfile-token-type').val(authResult.token_type);
                config.onAuth.call($(this.element), authResult.access_token);
            }
        }


        // Create and render a Picker object for searching images.
        // Visit here to find all the view ids:
        //   https://developers.google.com/picker/docs/reference#view-id
        function createPicker(oauthToken) {
            let allowedFileTypes = [
                'application/msword',
                'application/vnd.ms-powerpoint',
                'application/vnd.ms-excel',
                'application/vnd.google-apps.document',
                'application/vnd.google-apps.presentation',
                'application/vnd.google-apps.spreadsheet'
            ].join(',');

            let picker = new google.picker.PickerBuilder()
                .enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
                .enableFeature(google.picker.Feature.SUPPORT_DRIVES)
                .setOAuthToken(oauthToken)

                /* This is documented in the API but it's currently kind of broken
                 *
                 * You can't do what their example tells you...DOCS encompasses all the types
                 * and you can choose them from the search bar/dropdown in the modal, regardless
                 * of any other addView() commands here
                 */
                .addView(
                    new google.picker.DocsView()
                        .setOwnedByMe(true)
                        .setMimeTypes(allowedFileTypes)
                )
                .addView(
                    new google.picker.DocsView()
                        .setOwnedByMe(false)
                        .setMimeTypes(allowedFileTypes)
                )
                .addView(
                    new google.picker.DocsView()
                        .setEnableDrives(true)
                        .setMimeTypes(allowedFileTypes)
                )
                .addView(
                    new google.picker.DocsView()
                        .setLabel('Starred')
                        .setStarred(true)
                        .setMimeTypes(allowedFileTypes)
                )
                .addView(
                    new google.picker.DocsView(google.picker.ViewId.RECENTLY_PICKED)
                        .setLabel('Recent')
                        .setMimeTypes(allowedFileTypes)
                )
                .setCallback(pickerCallback)
                .build();
            picker.setVisible(true);

            /* Shut google modal when we shut our modal
             * https://stackoverflow.com/questions/13745505/how-to-use-zurb-foundation-reveal-with-open-opened-close-closed-callback-func
             */
            $(document).on('close.fndtn.reveal', config.modalId, function () {
                picker.setVisible(false);
            });
        }


        /**
         * Asynchronously execute this whenever you pick a file in the modal
         *
         * @param data
         */
        function pickerCallback(data) {
            var elem = $(this.element);

            if (data[google.picker.Response.ACTION] === google.picker.Action.PICKED) {
                config.onSelect.call(elem, data.docs);
                return;
            }

            if (data[google.picker.Response.ACTION] === google.picker.Action.CANCEL) {
                $(config.modalId).foundation('reveal', 'close');
            }
        }
    }
}(jQuery));
