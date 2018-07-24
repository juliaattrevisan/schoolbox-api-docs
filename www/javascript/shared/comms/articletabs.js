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

                // The component action bar, which will be hidden and shown based on if there's any articles
                // This can be left empty for implementations where there's no action bar
                componentActions: '',

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

                /* Show paginator
                 */
                showPaginator: false,

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

                // Show the component actions if the param is set and there's articles
                if (config.componentActions && response.empty) {
                    // Update href with the topic
                    config.componentActions.find('[data-news-component-view-all]').attr('href', response.viewAllLink);

                    // Show the action bar
                    config.componentActions.removeClass('hide');
                }

                /* Update the url if desired
                 */
                if (config.updateUrl) {
                    window.history.pushState('', response.title, response.url);
                }

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

                if (config.componentActions) {
                    // Hide the component actions
                    config.componentActions.addClass('hide');
                }

                /* Reload article list afresh
                 */
                getArticles($(this).attr('href'));
            });
        }
    }
}(jQuery));