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

        /* Only work when url is provided
         */
        if (config.firstUrl.length) {
            getArticles(config.firstUrl);
            initListeners();

            $(config.element).saveForLater().initAsync();
        }

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
                topicTabs: $(element).parents('[data-component]').find('[data-tab]'),

                /* Load this data upon start
                 */
                firstUrl: '',

                /* Return in compact mode or normal mode
                 */
                compactMode: false,

                /* Update the url upon tab change
                 * The url comes from backend, telling you the url of the topic requested
                 */
                updateUrl: false
            };

            return $.extend(defaults, options);
        }

        /**
         * Get articles via url specified
         */
        function getArticles(url) {

            /* TODO: Check for a cached version and load that, if possible; otherwise fetch it
             * Temporarily disabled due to action-at-a-distance with saved for later
             * and articles that appear in multiple topics with different saved for later states
             */

            $.ajax({
                type: 'GET',
                url: url,
                dataType: 'json'
            }).success(function (response) {

                /* Note that we don't need to reapply initSaveForLaterActions() again
                 */
                $(config.element).html(response.fragment).saveForLater();

                /* Update the url if desired
                 */
                if (config.updateUrl) {

                    /* Append pagenum if specified
                     * Otherwise, we changed tabs and want to clear away this param
                     */
                    if (config.pageNum) {
                        window.history.pushState('', response.title, response.url + '?p=' + config.pageNum);
                    }
                    else {
                        window.history.pushState('', response.title, response.url);
                    }
                }

                /* TODO: Cache the response so we don't need to refetch it
                 */

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

                /* Clear the current page number when changing topics
                 */
                config.pageNum = null;

                /* Reload article list afresh
                 */
                getArticles($(this).attr('href'));
            });
        }
    }
}(jQuery));