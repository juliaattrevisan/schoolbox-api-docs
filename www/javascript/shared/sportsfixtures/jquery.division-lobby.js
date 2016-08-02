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
                    var fromFeedback = findIndicator(from);
                    var toFeedback = findIndicator(to);

                    /* Reordering within the same sortable
                     */
                    if (from.is(to)) {
                    }
                    /* Dragging from division to a team
                     */
                    else if (isDivisionList(from) && isTeamPlayerList(to)) {
                        toFeedback.labelActivate({text: 'Adding to team...'});

                        /* Execute db transaciton
                         */
                        //console.log('dragging player from division to team ' + to.attr('data-test-team-player-list'));

                        fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                        toFeedback.labelTimeout({text: 'Added!', css: 'success'});
                    }
                    /* Dragging players between teams
                     */
                    else if (isTeamPlayerList(from) && isTeamPlayerList(to)) {
                        fromFeedback.labelActivate({text: 'Removing from team...'});
                        toFeedback.labelActivate({text: 'Adding to team...'});

                        /* Execute db transaciton
                         */
                        //console.log('dragging between teams - from: ' + from.attr('data-test-team-player-list') + ', to: ' + to.attr('data-test-team-player-list'));

                        fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                        toFeedback.labelTimeout({text: 'Added!', css: 'success'});
                    }
                    /* Remove a player from a team
                     */
                    else if (isTeamPlayerList(from) && isDivisionList(to)) {
                        fromFeedback.labelActivate({text: 'Removing from team...'});

                        /* Execute db transaciton
                         */
                        //console.log('remove a player from team ' + from.attr('data-test-team-player-list'));

                        fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                    }
                },
                over: function(event, ui) {

                    /* Reapply filter settings to division player list
                     */
                    //console.log('over');
                }
            });
        }

        /**
         * $element is a division's player list sortable
         *
         * @param element
         */
        function isDivisionList(element) {
            return element.attr('data-test-division-player-list');
        }

        /**
         * $element is a team's player list sortable
         *
         * @param element
         */
        function isTeamPlayerList(element) {
            return element.attr('data-test-team-player-list')
        }

        /**
         * Find the indicator for $element
         *
         * The division list and each team list has a label
         *
         * This method finds it in the destination, when a player is moved from one place to another
         * Also applies when dragging a person into the team's sortable while it's collapsed
         *
         * @param element
         * @returns {*}
         */
        function findIndicator(element) {
            if (isDivisionList(element)) {
                return $('[data-test-division-indicator]');
            }

            return element.parents('[data-test-team]').find('[data-test-team-indicator]');
        }
    }
}(jQuery));