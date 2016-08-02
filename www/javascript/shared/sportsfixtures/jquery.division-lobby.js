(function() {

    /**
     /* Every sortable is connected to every other sortable
     * This enables you to drag:
     *     within a sortable;
     *     between division and team sortables;
     *     between team and other team sortables
     *
     * @param options
     */
    $.fn.divisionLobby = function(options) {
        options = options || {};

        var config = init(options, $(this));

        initSortables();

        /**
         * Init
         *
         * @param options
         * @param element
         * @returns {*}
         */
        function init(options, element) {
            var defaults = {

                /* A list of all the connected sortables
                 * The division lobby and all its destination teams
                 */
                element: element
            };

            var derived = {
            };

            return $.extend(defaults, derived, options);
        }

        /**
         * Start up the sortable plugin
         */
        function initSortables() {
            $(config.element).sortable({
                handle: '.icon-grip',
                helper: 'clone',
                connectWith: $(config.element),
                start: function(event, ui) {

                    /* Always show the person being dragged away
                     */
                    $(ui.item).show();
                },
                stop: function(event, ui) {
                    var player = ui.item;
                    var from = $(event.target);
                    var to = player.closest('ul');

                    /* Indicators
                     */
                    var fromFeedback = from.closest('[data-team], [data-division]').find('[data-relation="save-label"]');
                    var toFeedback = from.closest('[data-team], [data-division]').find('[data-relation="save-label"]');

                    /* Dragging from division to a team
                     */
                    if (from.attr('data-test-division-player-list') && to.attr('data-test-team-player-list')) {
                        console.log('dragging player from division to team ' + to.attr('data-test-team-player-list'));
                    }
                    /* Dragging players between teams
                     */
                    else if (from.attr('data-test-team-player-list') && to.attr('data-test-team-player-list')) {
                        console.log('dragging between teams - from: ' + from.attr('data-test-team-player-list') + ', to: ' + to.attr('data-test-team-player-list'));
                    }
                    /* Remove a player from a team
                     */
                    else {
                        console.log('remove a player from team ' + from.attr('data-test-team-player-list'));
                    }
                },
                over: function(event, ui) {

                    /* Reapply filter settings to division player list
                     */
                    console.log('over');
                }
            });
        }
    }
}(jQuery));