/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
(function() {

    /**
     * Responsive IFrame
     *
     * @param options
     */
    $.fn.responsiveIframe = function(options) {
        options = options || {};

        var config = init(options, $(this));

        /* Initial state, if any
         */
        config.onLoad();

        /* Set up listeners
         */
        $(window).resize(function() {
            if ($(this).resizeTimeout) {
                clearTimeout($(this).resizeTimeout);
            }

            $(this).resizeTimeout = setTimeout(function () {
                $(this).trigger('resizeEnd');
            }, config.threshold);
        });

        $(window).bind('resizeEnd', function() {
            config.onResize();
        });

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

                /* Wait this long until triggering resizeEnd (ms)
                 */
                threshold: 400,

                /* Callback to execute upon init
                 */
                onLoad: function() {
                },

                /* Callback to execute upon resize
                 */
                onResize: function() {
                }
            };

            var derived = {
            };

            return $.extend(defaults, derived, options);
        }
    }
}(jQuery));
