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

                /* A set of urls used in the google api handshake process
                 */
                url: {
                    /* Scope
                     * List of scopes: https://developers.google.com/identity/protocols/oauth2/scopes
                     */
                    scope: 'https://www.googleapis.com/auth/drive',

                    /* The url to google's api.js file
                     * We need to load their js asynchronously, which gives us access to the
                     * object "gapi" that's used everywhere else
                     */
                    api: 'https://apis.google.com/js/api.js',
                },

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
            $.ajax({
                url: config.url.api,
                dataType: 'script',
                cache: true
            }).then(function () {
                let i, j;

                for (i = 0, j = 0; i < config.modules.length; ++i) {
                    gapi.load(config.modules[i], function () {

                        /* run gapi.load() on every config.module element, but only execute the callback
                         * when you're at the last config.module element; this causes callback() to occur
                         * only once, and after gapi.load() commences
                         *
                         * but how do you know the first one finished loading when the last module has
                         * also finished loading? You can't assert that from here...
                         */
                        if (++j === config.modules.length && config.callback && config.clientId.length) {
                            gapi.client.init({
                                'client_id': config.clientId,
                                'scope': config.url.scope
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
