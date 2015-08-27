(function() {

    /**
     * Search Plugin
     *
     * Loads a list of search results upon every instantiation
     *
     * @param options
     */
    $.fn.search = function(options) {
        options = options || {};

        var config = init(options, $(this));

        runOnce();

        setupMore();

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

                /* The search section (type) that was selected e.g. all, people, etc.
                 */
                section: '',

                /* The keyword to search for
                 */
                keyword: '',

                /* Default page number
                 */
                pageNumber: 1
            }

            var derived = {

                /* Navigation elements which you can use to see results for different items
                 * These elements contain data about which "section" you're viewing ie. the
                 * kind of item for which you are viewing search results - e.g. blog, news
                 */
                navElements: '#type dd',

                /* A comma-separated string/int tuple, e.g. all,1 / user,1 / user,2
                 *
                 * This is used to parameterise the search method. This interface is now
                 * expected each time a search is run.
                 */
                hash: '',

                /* "See More" elements. These trigger a dynamic hash page reload
                 */
                more: 'data-more',

                /* The actual section's HTML element that was selected
                 */
                sectionElement: $('[data-section=' + options.section + ']'),

                /* A set of results tabs. These are denoted by data-container=resultType,
                 * caching the selection here allows you to switch tabs faster
                 */
                resultsTabs: defaults.element.find('[data-result-container]'),

                /* The url to access asynchronously to populate different tabs
                 */
                url: '/resource/' + options.section + '/search',

                /* The div into which search results get loaded
                 */
                targetDiv: defaults.element.find('[data-type="'+options.section+'"]')
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Perform a search using the keyword, section and pageNumber to organise
         * results.
         */
        function runOnce() {

            /* Set up hash configuration
             */
            deriveHash(config.section, config.pageNumber);
            applyHash(config.hash);

            /* Load result set via ajax into target div
             */
            load();

            /* Hide all divs, and only show the current one
             */
            hideTabs();
        }

        /**
         * Load data in
         */
        function load() {
            if (!$(config.targetDiv).attr('data-loaded') === true) {
                var params = "keyword=" + config.keyword + "&type=" + config.section + "&p=" + config.pageNumber;

                $(config.targetDiv).load(config.url, params, function() {

                    /* Flag it as having loaded
                     */
                    $(config.targetDiv).attr('data-loaded', true);

                    /* Add pagination again
                     */
                    paginate();

                    updateStyles();
                });
            }
            else {
                /* Add pagination again
                 */
                paginate();

                updateStyles();
            }
        }

        /**
         * Add pagination events to all the <a> links
         */
        function paginate() {
            $('[data-search-pagination] [data-page]').bind('click', function(event) {
                event.preventDefault();

                /* Set the page number
                 */
                config.pageNumber = $(this).attr('data-page');

                /* Update hash configuration
                 */
                deriveHash(config.section, config.pageNumber);

                /* Slap the hash into the url
                 */
                applyHash(config.hash);

                /* Load search results in, update pagination and styles
                 */
                $(config.targetDiv).load(config.url, "keyword=" + config.keyword + "&type=" + config.section + "&p=" + config.pageNumber, function() {
                    paginate();
                    updateStyles();
                });
            });

            /* Show the pagination div again
             * but only for non-all portions
             */
            if (config.section == 'all') {
                $('[data-search-pagination]').hide();
            }
            else {
                $('[data-search-pagination]').show();
            }
        }

        /**
         * Put a new hash in the url
         */
        function applyHash(hash) {
            window.location.hash = hash;
        }

        /**
         * Derive a new url hash
         *
         * @param section
         * @param pageNumber
         */
        function deriveHash(section, pageNumber) {
            config.hash = section + ',' + pageNumber;
        }

        /**
         * Update UI styles
         *
         * @param sectionElement
         */
        function updateStyles() {

            /* Remove all selected classes
             */
            $(config.navElements).removeClass('active');

            /* Add active class to the thing you clicked on
             */
            config.sectionElement.parent().addClass('active');
        }

        /**
         * Hide irrelevant tabs
         */
        function hideTabs() {
            $(config.resultsTabs).hide();
            $(config.targetDiv).show();
        }

        /**
         * Set up seymour links
         */
        function setupMore() {
            $(config.element).on('click', '[' + config.more + ']', function(event) {
                event.preventDefault();

                var type = $(this).attr(config.more);

                window.location.hash = '#' + type + ',1';
                window.location.reload(true);
            });
        }
    }

    /**
     * Determine what the current hash configuration is, in the url. Split it
     * into its separate components
     */
    $.fn.fetchHash = function() {
        return window.location.hash.toString().substring(1, window.location.hash.length).split(',');
    }
}(jQuery));