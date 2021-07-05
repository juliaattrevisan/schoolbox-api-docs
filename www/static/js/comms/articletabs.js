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

                /* Update the url upon tab change
                 * The url comes from backend, telling you the url of the topic requested
                 */
                updateUrl: false,

                // Callback on tab change
                onChange: function () {},

                // Callback on articles loaded
                onChangeSuccess: function () {}
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

            // Fire onChange callback
            config.onChange();

            $.ajax({
                type: 'GET',
                url: url,
                dataType: 'json'
            }).done(function (response) {
                $(config.element).html(response.fragment);

                // Fire onChangeSuccess callback
                config.onChangeSuccess(response);

                /* Update the url if desired
                 */
                if (config.updateUrl) {
                    window.history.pushState('', response.title, response.url);
                }

            }).fail(function (response) {
            });
        }

        /**
         * Set up event listeners
         */
        function initListeners() {
            if (config.updateUrl) {
                window.addEventListener('popstate', function () {
                    window.location = location.href;
                });
            }

            $(config.topicTabs).on('click', 'a', function(event) {
                event.preventDefault();

                $(config.element).html('<div class="loader"></div>');

                /* Reload article list afresh
                 */
                getArticles($(this).attr('href'));
            });
        }
    }
}(jQuery));
