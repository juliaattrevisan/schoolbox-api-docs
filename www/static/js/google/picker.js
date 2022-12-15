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
        if (config.listener) {
            setupListeners();
        } else {
            if (config.authBtn) {
                $(config.authBtn).on('click', function () {
                    authorize();
                });
            }
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

                /* Our modal. The google modal appears on top of this or inside of this with containerID set
                 */
                modalId: '#gfile',

                /* To insert the Google Picker directly into a specific container within modalId, for when the picker is part of step by step process
                 */
                containerID: '',

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
                listener: true,
                authBtn: false,
            };
            $('#gfile-google-ids').val('');
            return $.extend(defaults, options);
        }


        /**
         * Set up listener
         */
        function setupListeners() {
            $(config.element).on('click', function () {
                authorize();
            });
        }


        /**
         * Ask google to setup picker
         */
        function authorize() {
            // This function will run when a response is received from window.googleClientToken.requestAccessToken().
            window.googleClientToken.callback = async (response) => {
                authResult = response;
                if (authResult.error !== undefined) {
                    throw authResult.error;
                }
                handleAuthResult();
            };

            if (authResult !== null) {
                // If authorisation has already been given, prompt the user to select a Google Account only.
                // This will happen if the Google File Selector is opened a second or subsequent time while the parent window has not been refreshed.
                window.googleClientToken.requestAccessToken({prompt: ''});
            } else if (config.authBtn && $(config.authBtn).hasClass('hide')) {
                $(config.authBtn).removeClass('hide');
            } else {
                // If authorisation has not yet been given, prompt the user to select a Google Account and ask for consent to share their data.
                window.googleClientToken.requestAccessToken({prompt: 'consent'});
            }
        }


        /**
         * Respond to google when you get past the authentication request
         * Spawn our modal and detect when we close the google modal; react accordingly
         */
        function handleAuthResult() {
            if (authResult && !authResult.error) {
                if (config.authBtn && !$(config.authBtn).hasClass('hide')) {
                    $(config.authBtn).addClass('hide');
                }
                $('#gfile-access-token').val(authResult.access_token);
                $('#gfile-token-type').val(authResult.token_type);
                config.onAuth.call($(this.element), authResult.access_token);
            }
        }

        // Create and render a Picker object for searching images.
        // Visit here to find all the view ids:
        //   https://developers.google.com/picker/docs/reference#view-id
        function createPicker() {
            let builder = new google.picker.PickerBuilder();

            let features = config.features.length > 0 ? config.features :
                [
                    google.picker.Feature.MULTISELECT_ENABLED,
                    google.picker.Feature.SUPPORT_DRIVES
                ]
            ;
            let views = config.views.length > 0 ? config.views : getDefaultViews();

            // Enable selected features.
            for (let i = 0; i < features.length; ++i) {
                builder.enableFeature(features[i]);
            }

            // Enabled selected views.
            for (let i = 0; i < views.length; ++i) {
                builder.addView(views[i]);
            }

            let picker = builder
                .setOAuthToken(authResult.access_token)
                .setCallback(pickerCallback)
                .hideTitleBar()
                .build();
            picker.setVisible(true);

            if (config.containerID && picker.De !== undefined) { // If the container exists, move the picker into the empty container and ensure the picker sits at the top left.
                picker.De.style.left = '0px';
                picker.De.style.top = '0px';
                picker.De.style.width = '100%'; // Ensure it sits within the boundaries of the parent modal.
                $(config.containerID).append(picker.De);
                window.addEventListener('resize', resizeGooglePicker);
            }

            /* Shut google picker modal when we shut our modal
             * https://stackoverflow.com/questions/13745505/how-to-use-zurb-foundation-reveal-with-open-opened-close-closed-callback-func
             */
            $(document).on('close.fndtn.reveal', config.modalId, onClosePicker);

            /**
             * Google has its own resize listener, so we need to overwrite it to ensure the picker stays within the
             * boundaries of its parent modal.
             */
            function resizeGooglePicker () {
                picker.De.style.width = '100%';
            }

            /**
             * Clean up after the picker modal has been closed.
             */
            function onClosePicker() {
                picker.setVisible(false);
                if (config.containerID) { // If the container exists, return it to the state it was in before the call to createPicker().
                    window.removeEventListener('resize', resizeGooglePicker);
                    $(config.containerID).empty();
                }

                $(document).off('close.fndtn.reveal', config.modalId, onClosePicker);
            }
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
                if (config.containerID) { //empty on close
                    $(config.containerID).empty();
                }
            }
        }
    }
}(jQuery));
