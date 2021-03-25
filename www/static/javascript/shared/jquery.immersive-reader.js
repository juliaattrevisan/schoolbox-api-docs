(function($) {

    /**
     * Constructor
     *
     * @param options
     */
    $.fn.immersiveReader = function(options) {
        options = options || {};

        /* Store the config in this instance so private methods
         * can access it
         */
        var config = init(options, this);

        execute();

        /**
         * Set up options
         *
         * @param {Object} options element: the button element, defaults to the element the plugin was called on
         *                         title:   the title of the immersive reader
         *                         article: the article to read in the reader, defaults to the first article in the
         *                                  button's parent
         * @param {jQuery} element the element the plugin was called on
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,
                title: 'Immersive Reader',
                article: $(element).parent().find('article')[0],
            }

            var derived = {
            };

            return $.extend(defaults, derived, options);
        }


        function getTokenAndSubdomainAsync() {
            return new Promise(function (resolve, reject) {
                $.ajax({
                    url: "/immersive-reader/getAuth",
                    type: "GET",
                    success: function (data) {
                        if (data.error) {
                            reject(data.error);
                        } else {
                            resolve(data);
                        }
                    },
                    error: function (err) {
                        reject(err);
                    }
                });
            });
        }

        function handleLaunchImmersiveReader(title,content) {
            getTokenAndSubdomainAsync()
                .then(function (response) {
                    const token = response["token"];
                    const subdomain = response["subdomain"];

                    // Learn more about chunk usage and supported MIME types https://docs.microsoft.com/en-us/azure/cognitive-services/immersive-reader/reference#chunk
                    const data = {
                        title: title,
                        chunks: [{
                            content: content,
                            mimeType: "text/html"
                        }]
                    };

                    // Learn more about options https://docs.microsoft.com/en-us/azure/cognitive-services/immersive-reader/reference#options
                    const options = {
                        "uiZIndex": 2000
                    };

                    ImmersiveReader.launchAsync(token, subdomain, data, options)
                        .catch(function (error) {
                            console.log(error);
                        });
                })
                .catch(function (error) {
                    console.log(error);
                });
        }

        /**
         * Execute plugin
         */
        function execute() {
            var button = config.element;
            $(button).on('click',  function(event) {
                event.preventDefault();
                handleLaunchImmersiveReader(config.title, config.article.innerHTML)
            });
        }
    }
}(jQuery));
