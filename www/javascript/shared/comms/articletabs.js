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

        /* Only work initially when url is provided
         * Otherwise, it must load upon topic change
         */
        if (config.selectedUrl.length) {
            getArticles(config.selectedUrl);
        }

        /* Set everything else up as normal
         */
        initListeners();

        $(config.element).saveForLater().initAsync();


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

                /* Asynchronously load this data upon start
                 * If empty, it'll do nothing until you change topics
                 */
                selectedUrl: '',

                /* Return in compact mode or normal mode
                 */
                compactMode: false,

                /* Update the url upon tab change
                 * The url comes from backend, telling you the url of the topic requested
                 */
                updateUrl: false,

                /* The current page number
                 */
                pageNum: -1,

                /* The current limit
                 */
                limit: null
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

                    /* TODO Surely there's a nicer way than this; I'll find out tomorrow I guess
                     */
                    var querystringParams = [
                        'p=' + config.pageNum,
                        'l=' + config.limit
                    ].join('&');

                    /* Append pagenum if specified
                     * Otherwise, we changed tabs and want to clear away this param
                     */
                    if (config.pageNum) {
                        window.history.pushState('', response.title, response.url + '?p' + querystringParams);
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