/**
 * Allow toggling of tabs based on what was last clicked
 * to spawn the currently active (visible) tab
 *
 * Foundation applies .content.active and you can't remove
 * it directly - that occurs after the event listener stuff
 * here. Set a timeout to wait for foundation to do its thing,
 * which we then ignore and do OUR thing, which is to remove
 * the .active class, effectively hiding the tab.
 *
 * All tied into already-established ids, so you only need to
 * add data-tab-activator to your top-level tab spawners.
 */
(function() {

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.tabActivator = function(options) {
        options = options || {};

        var config = init(options, $(this));

        tabActivator();

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
                activator: 'data-tab-activator',
                onTabClose: function() {
                }
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Allow toggling of tabs based on what was last clicked
         * to spawn the currently active (visible) tab
         */
        function tabActivator() {
            $('['+config.activator+']').click(function() {

                /* Tab corresponding to this activator
                 */
                var tab = $(this).attr('href');

                /* If this tab activator wasn't already active,
                 * just use default tab-switching behaviour.
                 *
                 * Otherwise, toggle tab
                 */
                if ($(this).data(config.activator) == true) {

                    /* Close this tab if it's open
                     * Otherwise just use default behaviour
                     */
                    if ($(tab).hasClass('active')) {

                        /* Wait a little bit for foundation to do its crazy crap,
                         * then surreptitiously do our own crazy crap
                         */
                        setTimeout(function() {
                            $(tab).toggleClass('active');

                            config.onTabClose();
                        }, 100);
                    }
                }
                else {

                    /* Clear out every other activator's setting
                     */
                    $('[data-tab-activator]').data(config.activator, false);

                    /* Add a flag to remember this tab activator is active,
                     * so toggling can take place
                     */
                    $(this).data(config.activator, true);
                }
            });
        }
    }
}(jQuery));