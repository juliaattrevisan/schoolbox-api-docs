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
                pageLength: 40
            };

            return $.extend(defaults, options);
        }


        /**
         * Add events for each socket
         */
        function initSockets(sockets) {
            sockets.each(function(index, element) {
                $(element).on('click', function(event) {
                    alert($(this).attr('src'));
                });
            });
        }


        /**
         * Load the first set of icons when you start a new category
         */
        function initIconCategorySelector() {
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

                loadPage(1, config.registry[folder].num);
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
            for (let i = start; i <= (start+config.pageLength) && i <= max; i++, imgIndex++) {
                let src = '/images/badges/' + folder + '/' + i + '.svg';

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
            $('[data-previous], [data-next]').unbind('click');

            /* "Next" availability
             */
            if (start+config.pageLength < max) {
                $('[data-next]').on('click', function(event) {
                    loadPage(start+config.pageLength, max);
                }).removeClass('hide');
            } else {
                $('[data-next]').addClass('hide');
            }

            /* "Previous" availability
             */
            if (start > 1) {
                $('[data-previous]').on('click', function(event) {
                    loadPage(start-config.pageLength, max);
                }).removeClass('hide');
            } else {
                $('[data-previous]').addClass('hide');
            }
        }
    }
}(jQuery));