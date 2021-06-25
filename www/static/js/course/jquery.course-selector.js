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

                    // any additional params to send with the AJAX request
                    query: {
                        hasBreadcrumb: false,
                        hasAdd:        false,
                        hasTot:        true,
                        linkToUnits:   false
                    },

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

            return $.extend(true, {}, defaults, options);
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
         * stopped typing before firing an ajax missile out
         */
        function courseSelector() {

            /* After you finish typing, spawn search results
             */
            $('body').on('keydown', config.courseSelector.filter, function(event) {
                if (this.courseFilterTimeout) {
                    clearTimeout(this.courseFilterTimeout);
                }

                this.courseFilterTimeout = setTimeout(function () {

                    // clone the params so we can extend them
                    var params = $.extend(true, {}, config.courseSelector.query);

                    // load the query into the params
                    params.search = $(config.courseSelector.filter).val();

                    addLoadingState(config.courseSelector.filter);

                    $.get(config.courseSelector.url, params, function(responseText) {
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

                /* The element acted upon by plugin
                 */
                element: element,

                /* The element which activates the Course Selector
                 * in its data var format, and as an internally-used
                 * version for .data() accessor
                 */
                activator: {
                    selector: '[data-course-selector]',
                    instanceID: 'course-selector',
                    folderID:   'course-folder'
                },

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
                },

                onCourseSelect: function() {
                }
            };

            return $.extend(true, {}, defaults, options);
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
            $(document).on('click', config.activator.selector, function(event) {
                event.preventDefault();
                var query = {};

                // Find the folder ID that the course should be imported to
                var folderID = $(this).data(config.activator.folderID);
                if(folderID) {
                    query.folder = folderID;
                }
                else {
                    query = $.extend(true, {}, config.courseSelector.query);
                }

                // Find the component instance ID that the course should be attached to
                var compInstId = $(this).data(config.activator.instanceID);
                if (compInstId) {
                    query.component = compInstId;
                }

                config.courseSelector.modal.foundation('reveal', 'open', {
                    url: config.courseSelector.url,
                    data: query
                });
            });

            /* Callbacks
             */
            config.onCourseSelect();
        }
    }
}(jQuery));
