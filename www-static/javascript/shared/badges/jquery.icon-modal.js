(function() {

    /**
     * Badge Icon Modal
     *
     * Pick your badge svg
     *
     * @param options
     */
    $.fn.iconModal = function(options) {
        options = options || {};

        let config = init(options, $(this));
        let folder = '';

        initSockets(config.sockets);
        initIconCategorySelector();


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

                /* Placeholder img tags
                 */
                sockets: $('[data-icon]'),

                /* Default number of icons per page
                 */
                pageLength: 40,

                /* Paginator elements
                 */
                paginators: $('[data-previous], [data-next]'),

                /* Landing page that presumably explains something useful
                 */
                landing: 'education',

                /* Images are found at this relative url
                 */
                root: '/images/badges',

                /* Executes when you click on an icon
                 */
                onSelect: function(element) {
                }
            };

            return $.extend(defaults, options);
        }


        /**
         * Add events for each socket
         */
        function initSockets(sockets) {
            sockets.each(function(index, element) {
                $(element).on('click', function(event) {
                    config.onSelect($(this))
                });
            });

        }


        /**
         * Load the first set of icons when you start a new category
         */
        function initIconCategorySelector() {
            folder = config.landing;
            $('[data-icon-path='+config.landing+']').addClass('active');
            loadPage(0, config.registry[folder].files.length);

            $('[data-icon-path]').on('click', function(event) {
                let current = $(this).attr('data-icon-path');

                /* Already current folder; noop
                 */
                if (folder === current) {
                    return;
                }

                /* Change category
                 */
                folder = current;
                $('[data-icon-path]').removeClass('active')
                $(this).addClass('active');

                loadPage(0, config.registry[folder].files.length);
            });

        }


        /**
         * Fetch a new set of icons
         *
         * @param start
         * @param max
         */
        function loadPage(start, max) {
            let imgIndex = 0;

            /* Clear template
             */
            config.sockets.attr('src', '').addClass('hide');

            /* Reload icon set
             */
            for (let i = start; i < (start+config.pageLength) && i < max; i++, imgIndex++) {
                let src = config.root + '/' + folder + '/' + config.registry[folder].files[i];

                $(config.sockets[imgIndex]).attr('src', src).removeClass('hide');
            }

            /* Decide what the paginator buttons do now
             * Sometimes they should be hidden; other times they should load data
             */
            initPaginators(start, max);
        }


        /**
         * Add paginator buttons and behaviours conditionally
         *
         * @param start
         * @param max
         */
        function initPaginators(start, max) {

            /* Clear events for paginator buttons
             */
            config.paginators.unbind('click');

            /* "Next" availability
             */
            if (start+config.pageLength < max) {
                $('[data-next]').on('click', function(event) {
                    loadPage(start+config.pageLength, max);
                }).removeClass('disabled');
            } else {
                $('[data-next]').addClass('disabled');
            }

            /* "Previous" availability
             */
            if (start > 1) {
                $('[data-previous]').on('click', function(event) {
                    loadPage(start-config.pageLength, max);
                }).removeClass('disabled');
            } else {
                $('[data-previous]').addClass('disabled');
            }
        }
    }
}(jQuery));
