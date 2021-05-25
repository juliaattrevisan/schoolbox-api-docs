(function () {

    /**
     * Google Picker
     *
     * Pick files from Google, and do something with them: generally, they will
     * be uploaded to Schoolbox's file system.
     *
     * @param options
     */
    $.fn.googlePicker = function (options) {
        options = options || {};

        let config = init(options, $(this));
        let authResult = null;

        /* Only proceed if both parts set
         */
        if (config.clientId.length && config.modules.length) {
            setupListeners();
        } else {
            authorize();
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
                features: [],
                views: [],
                mimeTypes: [],
                /* Callback
                 */
                onSelect: function () {
                },
                onAuth: function (e) {
                    if (config.modalId) {
                        $(config.modalId).foundation('reveal', 'open');
                    }
                    createPicker(e);
                },
                /* A google setting
                 */
                scope: 'https://www.googleapis.com/auth/drive',
                modules: []
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
                authResult = gUser.getAuthResponse();
                if (gUser.isSignedIn()) {
                    handleAuthResult();
                } else {
                    gAuth.signIn({'prompt': 'select_account'}).then(
                        function () {
                            handleAuthResult();
                        }
                    );
                }
            });
        }


        /**
         * Respond to google when you get past the authentication request
         * Spawn our modal and detect when we close the google modal; react accordingly
         */
        function handleAuthResult() {
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
            let builder = new google.picker.PickerBuilder();

            let features = config.features.length > 0 ? config.features :
                [
                    google.picker.Feature.MULTISELECT_ENABLED,
                    google.picker.Feature.SUPPORT_DRIVES
                ]
            ;
            let views = config.views.length > 0 ? config.views : getDefaultViews();

            // enable selected features
            for (i = 0; i < features.length; ++i) {
                builder.enableFeature(features[i]);
            }

            // enabled selected views
            for (i = 0; i < views.length; ++i) {
                builder.addView(views[i]);
            }
            let picker = builder
                .setOAuthToken(oauthToken)
                .setCallback(pickerCallback)
                .hideTitleBar()
                .build();
            picker.setVisible(true);

            /* Shut google modal when we shut our modal
             * https://stackoverflow.com/questions/13745505/how-to-use-zurb-foundation-reveal-with-open-opened-close-closed-callback-func
             */
            $(document).on('close.fndtn.reveal', config.modalId, function () {
                picker.setVisible(false);
            });
        }


        /* This is documented in the API but it's currently kind of broken
         *
         * You can't do what their example tells you...DOCS encompasses all the types
         * and you can choose them from the search bar/dropdown in the modal, regardless
         * of any other addView() commands here
         */
        function getDefaultViews() {
            let views = [
                new google.picker.DocsView()
                    .setOwnedByMe(true),
                new google.picker.DocsView()
                    .setOwnedByMe(false),
                new google.picker.DocsView()
                    .setEnableDrives(true),
                new google.picker.DocsView()
                    .setLabel('Starred')
                    .setStarred(true),
                new google.picker.DocsView(google.picker.ViewId.RECENTLY_PICKED)
                    .setLabel('Recent'),
            ];
            views.forEach(function (e) {
                e.setIncludeFolders(true)
                    .setSelectFolderEnabled(false)
                    .setMimeTypes(config.mimeTypes.join(','));
            });
            return views;
        }

        /**
         * Asynchronously execute this whenever you pick a file in the modal
         *
         * @param data
         */
        function pickerCallback(data) {
            var elem = $(this.element);

            if (data[google.picker.Response.ACTION] === google.picker.Action.PICKED) {
                config.onSelect.call(elem, data.docs, authResult);
                return;
            }

            if (data[google.picker.Response.ACTION] === google.picker.Action.CANCEL) {
                if (config.modalId) {
                    $(config.modalId).foundation('reveal', 'close');
                }
            }
        }
    }
}(jQuery));
