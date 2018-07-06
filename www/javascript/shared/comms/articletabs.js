/**
 * Async load articles for a topic
 */
(function() {

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.articleTabs = function(options) {
        options = options || {};

        var config = init(options, $(this));

        getArticles(config.firstUrl);
        initListeners();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {
                element: element,

                /* The ul containing tabs that trigger async requests
                 */
                topicTabs: null,

                /* Cache for async responses
                 */
                cache: [],

                /* Load this data upon start
                 */
                firstUrl: ''
            };

            return $.extend(defaults, options);
        }

        /**
         * Get articles via url specified
         */
        function getArticles(url) {

            /* Check for a cached version and load that, if possible
             * Otherwise fetch it
             *
             * Temporarily disabled due to action-at-a-distance with saved for later
             * and articles that appear in multiple topics with different saved for later states
             */
            // if (config.cache[url] !== undefined) {
            //     $(config.element).html(config.cache[url]).saveForLater();
            //     return;
            // }

            $.ajax({
                type: 'GET',
                url: url,
                dataType: 'html'
            }).success(function (response) {
                $(config.element).html(response).saveForLater();

                /* Cache the response so we don't need to refetch it
                 */
                // config.cache[url] = response;
            }).error(function (response) {
            });
        }

        /**
         * Set up event listeners
         */
        function initListeners() {
            $(config.topicTabs).on('click', 'a', function(event) {
                event.preventDefault();

                $(config.element).html('<div class="loader"></div>');

                getArticles($(this).attr('href'));
            });
        }
    }
}(jQuery));