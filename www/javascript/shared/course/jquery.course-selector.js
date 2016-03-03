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
                    url: '/course/list',

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
                    delay: 500
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
                    $(config.courseSelector.filter).css('background',"url('/images/core/ajax-loader.gif') no-repeat 99%");
                    var query = $(config.courseSelector.filter).val();

                    $.get('/course/list/ajax', { query: query}, function(responseText) {
                        $(config.courseSelector.modal).find('#results').html(responseText);
                        $(config.courseSelector.filter).css('background', '');
                    });
                }, config.courseSelector.delay);
            });

            /* Always focus on the search field upon spawn
             */
            $(document).on('opened.fndtn.reveal', config.courseSelector.modalIdentifier, function() {
                config.courseSelector.modal.find(config.courseSelector.filter).focus();
            });

            /* Spawn modal
             */
            config.element.on('click', function(event) {
                event.preventDefault();

                config.courseSelector.modal.foundation('reveal', 'open', {
                    url: config.courseSelector.url
                });
            });
        }
    }
}(jQuery));
