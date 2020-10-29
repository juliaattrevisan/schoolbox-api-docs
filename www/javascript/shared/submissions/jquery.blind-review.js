(function() {

    /**
     * Blind Review
     *
     * Choose a random person who has a pending response,
     * visit their inline grading screen and obfuscate
     * any identifying features
     *
     * @param options
     */
    $.fn.blindReview = function(options) {
        options = options || {};

        let config = init(options, $(this));

        blindReview();

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
                params: ''
            };

            return $.extend(defaults, options);
        }

        /* Start from a random place
         */
        function blindReview() {
            let pendingResponses = $('[data-assessment-status-light="pending"]');

            /* Select one randomly
             */
            let selected = Math.floor(Math.random() * pendingResponses.length);

            /* Set up url with params
             */
            let url = $(pendingResponses[selected]).parents('[data-participant]').find('[data-inline-grader]').attr('href');
            let blindUrl = url + config.params;

            /* Nobody has any pending responses; disable button
             */
            if (url === undefined) {
                $(config.element).hide();
            } else {
                $(config.element).show().attr('href', blindUrl);
            }
        }
    }
}(jQuery));
