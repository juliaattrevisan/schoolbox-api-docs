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
                compactMode: false
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
                dataType: 'html'
            }).success(function (response) {

                /* Note that we don't need to reapply initSaveForLaterActions() again
                 */
                $(config.element).html(response).saveForLater();

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

                getArticles($(this).attr('href'));
            });
        }
    }
}(jQuery));