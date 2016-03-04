(function() {

    /**
     * Course Selector Modal
     *
     * @param options
     */
    $.fn.courseSelector = function(options) {
        options = options || {};

        var config = init(options, $(this));

        courseSelector();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {

                /* The element which activates the Course Selector
                 */
                element: element,

                courseSelector: {

                    /* Where the Course List is located
                     */
                    url: '/course/list/ajax',

                    /* Ajax requests spawn inside this element
                     * These should reference the same element
                     */
                    modalIdentifier: '#course-selector-modal',
                    modal: $('#course-selector-modal'),

                    /* This element is used to filter the ajax request contents
                     */
                    filter: '[data-course-selector-filter]',

                    /* Ajax update occurs $delay milliseconds after last keypress
                     * is detected in config.courseSelector.filter
                     */
                    delay: 500,

                    /* CSS rule for indicating searching is happening
                     */
                    loadingCss: 'course-selector-search-loading'
                }
            };

            var derived = {};

            return $.extend(defaults, derived, options);
        }

        /**
         * As you change the Filter field, regenerate a list of matching
         * Courses that were found. Setting Filter field to empty restores
         * the original list of all Courses
         *
         * [data-course-selector-filter] doesn't always exist, so you have
         * to put this on an element which DOES always exist >.<
         *
         * Delay as you type on keydown, so it waits a second after you've
         * stopped typing before firing an ajax missle out
         */
        function courseSelector() {

            /* After you finish typing, spawn search results
             */
            $('body').on('keydown', config.courseSelector.filter, function(event) {
                if (this.courseFilterTimeout) {
                    clearTimeout(this.courseFilterTimeout);
                }

                this.courseFilterTimeout = setTimeout(function () {
                    var query = $(config.courseSelector.filter).val();

                    addLoadingState(config.courseSelector.filter);

                    $.get(config.courseSelector.url, { query: query}, function(responseText) {
                        $('#course-selector-results').html(responseText);

                        removeLoadingState(config.courseSelector.filter);
                    });
                }, config.courseSelector.delay);
            });
        }

        /**
         * Add loading state style
         *
         * @param element
         */
        function addLoadingState(element) {
            $(element).addClass(config.courseSelector.loadingCss);
        }

        /**
         * Remove loading state style
         *
         * @param element
         */
        function removeLoadingState(element) {
            $(element).removeClass(config.courseSelector.loadingCss);
        }
    }
}(jQuery));

(function() {

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.courseSelectorModal = function(options) {
        options = options || {};

        var config = init(options, $(this));

        setupModal();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {

                /* The element which activates the Course Selector
                 */
                element: element,

                courseSelector: {

                    /* Where the Course List is located
                     */
                    url: '/course/list',

                    /* Ajax requests spawn inside this element
                     * These should reference the same element
                     */
                    modalIdentifier: '#course-selector-modal',
                    modal: $('#course-selector-modal'),

                    /* This element is used to filter the ajax request contents
                     */
                    filter: '[data-course-selector-filter]'
                }
            };

            var derived = {
            };

            return $.extend(defaults, derived, options);
        }

        /**
         * Set up modal behaviour
         */
        function setupModal() {
            $(document).on('opened.fndtn.reveal', config.courseSelector.modalIdentifier, function () {
                config.courseSelector.modal.find(config.courseSelector.filter).focus();
            });

            /* Spawn modal
             */
            $(document).on('click', '[data-course-selector]', function(event) {
                event.preventDefault();

                config.courseSelector.modal.foundation('reveal', 'open', {
                    url: config.courseSelector.url
                });
            });
        }
    }
}(jQuery));
