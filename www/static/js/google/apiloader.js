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

                /* The url to google's api.js file
                 * We need to load their js asynchronously, which gives us access to the
                 * object "gapi" that's used everywhere else
                 */
                url: "https://apis.google.com/js/api.js",

                /* A list of modules for which to execute the callback provided
                 */
                modules: [],

                /* A callback to execute when the last of the modules has loaded
                 * This will always occur after gapi.client.init() completes
                 */
                callback: function () {},
            };

            return $.extend(defaults, options);
        }

        /**
         * Set up the gapi global object
         */
        function setup() {
            $.ajax({
                url: config.url,
                dataType: "script",
                cache: true,
            }).then(function () {
                let nModulesLoaded = 0;
                for (let i = 0; i < config.modules.length; ++i) {
                    gapi.load(config.modules[i], function () {
                        /* Run gapi.load() for every config.module, but only execute the callback after the last
                         * module has loaded.  This causes callback() to be run only once.
                         */
                        if (++nModulesLoaded === config.modules.length && config.callback) {
                            config.callback();
                        }
                    });
                }
            });
        }
    };
})(jQuery);
