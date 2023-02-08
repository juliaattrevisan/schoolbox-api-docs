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
                $(config.authBtn).on("click", function () {
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
                googleAccessToken: "",
                googleAccessTokenType: "",

                /* The link which activates the Google picker
                 */
                element: element,

                /* Google Apps Client ID (googlapps_client_id in config database table).
                 */
                clientId: element,

                /* Our modal. The Google modal appears on top of this.
                 */
                modalId: "#gfile",

                postGoogleFileUrl: "", // TODO: Remove this (and from comment in googleFiles.twig)?
                features: [],
                views: [],
                mimeTypes: [],
                /* Callback
                 */
                onSelect: function () {},
                onAuth: function (e) {
                    if (config.modalId) {
                        $(config.modalId).foundation("reveal", "open");
                        $(config.modalId).hide(); // Hide the second modal until a file has been picked.
                    }
                    createPicker(e);
                },
                listener: true,
                authBtn: false,
            };
            $("#gfile-google-ids").val("");
            return $.extend(defaults, options);
        }

        /**
         * Set up listener
         */
        function setupListeners() {
            $(config.element).on("click", function () {
                authorize();
            });
        }

        /**
         * TODO:
         * Maybe code pertaining to authorisation should be removed from the googlePicker() function, since it is not specific to the Google Picker.
         * (it is required for the Google Drive module also.  @See library/Schoolbox/Resource/GoogleFile/templates/detail.twig).
         */
        function authorize() {
            // The google.accounts.oauth2 variable comes from https://accounts.google.com/gsi/client.
            let googleClientToken = window.google.accounts.oauth2.initTokenClient({
                client_id: config.clientId,
                scope: "https://www.googleapis.com/auth/drive", // See, edit, create, and delete all of your Google Drive files (see https://developers.google.com/identity/protocols/oauth2/scopes).
                callback: (response) => {},
            });

            // This function will run when a response is received from window.googleClientToken.requestAccessToken().
            googleClientToken.callback = async (response) => {
                authResult = response;
                if (authResult.error !== undefined) {
                    throw authResult.error;
                }
                handleAuthResult(true);
            };

            if (config.googleAccessToken !== "" && config.googleAccessTokenType !== "") {
                // We already have the googleAccessToken (retrieved from the PHP session), so mimic a successful
                // response to googleClientToken.requestAccessToken().
                // PROBLEM: The token retrieved from the PHP session may have expired.  How do we check for that?
                authResult = {
                    access_token: config.googleAccessToken,
                    token_type: config.googleAccessTokenType,
                };
                handleAuthResult(false); // Do not send the token to the server, because that's where it came from.
            } else if (config.authBtn && $(config.authBtn).hasClass("hide")) {
                // If the auth button is hidden, show the button so the user can click to authorise access to Google files.
                $(config.authBtn).removeClass("hide");
            } else {
                googleClientToken.requestAccessToken({ prompt: "" }); // Setting prompt to blank tells Google to prompt for authorisation only if necessary.
            }
        }

        /**
         * Respond to Google when you get past the authentication request
         * Spawn our modal and detect when we close the Google modal; react accordingly
         */
        function handleAuthResult(sendAccessTokenToServer) {
            if (authResult && !authResult.error) {
                if (config.authBtn && !$(config.authBtn).hasClass("hide")) {
                    $(config.authBtn).addClass("hide");
                }
                $("#gfile-access-token").val(authResult.access_token);
                $("#gfile-token-type").val(authResult.token_type);
                config.onAuth.call($(this.element), authResult.access_token);

                if (sendAccessTokenToServer) {
                    // Store authResult.access_token and authResult.token_type to a PHP session so that repeated use of the
                    // Google picker will not require repeated authorisation.
                    // This happens in the background, so we don't need to do anything in response to ajax success or error.
                    // In case of error, the user will just have to re-authorise every time they visit a new page and try to
                    // use the Google Picker (since the token will not exist in $_SESSION at the server, and so it will not
                    // have been passed to the client during page generation).
                    $.ajax({
                        data: { accessToken: authResult.access_token, tokenType: authResult.token_type },
                        dataType: "json",
                        method: "post",
                        url: "/googleFile/saveAccessTokenToSession",
                    });
                }
            }
        }

        /**
         * Create and render a Picker object for searching images.
         * Visit here to find all the view ids:
         *   https://developers.google.com/picker/docs/reference#view-id
         */
        function createPicker() {
            let builder = new google.picker.PickerBuilder();

            let features =
                config.features.length > 0
                    ? config.features
                    : [google.picker.Feature.MULTISELECT_ENABLED, google.picker.Feature.SUPPORT_DRIVES];
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

            /* Shut Google picker modal when we shut our modal
             * https://stackoverflow.com/questions/13745505/how-to-use-zurb-foundation-reveal-with-open-opened-close-closed-callback-func
             */
            $(document).on("close.fndtn.reveal", config.modalId, onClosePicker);

            /**
             * Clean up after the picker modal has been closed.
             */
            function onClosePicker() {
                picker.setVisible(false);
                $(document).off("close.fndtn.reveal", config.modalId, onClosePicker);
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
                new google.picker.DocsView().setOwnedByMe(true),
                new google.picker.DocsView().setOwnedByMe(false),
                new google.picker.DocsView().setEnableDrives(true),
                new google.picker.DocsView().setLabel("Starred").setStarred(true),
                new google.picker.DocsView(google.picker.ViewId.RECENTLY_PICKED).setLabel("Recent"),
            ];
            views.forEach(function (e) {
                e.setIncludeFolders(true).setSelectFolderEnabled(false).setMimeTypes(config.mimeTypes.join(","));
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
                $(config.modalId).show(); // Reveal the second modal now that a file has been picked.
                config.onSelect.call(elem, data.docs, authResult);
                return;
            }

            if (data[google.picker.Response.ACTION] === google.picker.Action.CANCEL) {
                if (config.modalId) {
                    $(config.modalId).foundation("reveal", "close");
                }
            }
        }
    };
})(jQuery);
