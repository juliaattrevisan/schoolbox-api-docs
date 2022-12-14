(function () {

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.setupGapi = function (options) {
        options = options || {};

        let config = init(options, $(this));

        setup();

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

                /* From googleapps_client_id
                 */
                clientId: '',

                /* Scope
                 * List of scopes: https://developers.google.com/identity/protocols/oauth2/scopes
                 */
                scope: 'https://www.googleapis.com/auth/drive', // See, edit, create, and delete all of your Google Drive files (see https://developers.google.com/identity/protocols/oauth2/scopes).

                /* The url to google's api.js file
                 * We need to load their js asynchronously, which gives us access to the
                 * object "gapi" that's used everywhere else
                 */
                url: 'https://apis.google.com/js/api.js',

                /* A list of modules for which to execute the callback provided
                 */
                modules: [],

                /* A callback to execute when the last of the modules has loaded
                 * This will always occur after gapi.client.init() completes
                 */
                callback: function () {
                }
            };

            return $.extend(defaults, options);
        }


        /**
         * Set up the gapi global object
         */
        function setup() {
            // The google.accounts.oauth2 variable comes from https://accounts.google.com/gsi/client (included in templates/core/homepage/index.html).
            window.googleClientToken = google.accounts.oauth2.initTokenClient({
                client_id: config.clientId,
                scope: config.scope,
                callback: (response) => {
                },
            });

            $.ajax({
                url: config.url,
                dataType: 'script',
                cache: true
            }).then(function () {
                if (config.modules.length === 0) {
                    config.modules = ['client']; // NOTE: gapi.client (used below) will not exist unless we load the 'config' module.
                }

                for (let i = 0, j = 0; i < config.modules.length; ++i) {
                    gapi.load(config.modules[i], function () {
                        /* run gapi.load() on every config.module element, but only execute the callback
                         * when you're at the last config.module element; this causes callback() to occur
                         * only once, and after gapi.load() commences
                         *
                         * but how do you know the first one finished loading when the last module has
                         * also finished loading? You can't assert that from here...
                         */
                        if (++j === config.modules.length && config.callback && config.clientId.length) {
                            // Not sure whether this is still needed after all uses of the 'client:auth2' module were removed in SA-14475.
                            gapi.client.init({
                                'client_id': config.clientId,
                                'scope': config.scope
                            }).then(function () {
                                config.callback();
                            });
                        }
                    });
                }
            });
        }
    }
}(jQuery));
