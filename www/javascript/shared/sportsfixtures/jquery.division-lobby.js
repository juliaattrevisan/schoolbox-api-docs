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

                    /* For debugging only
                     */
                    var fromId;
                    var toId;

                    /* Reordering within the same sortable
                     */
                    if (from.is(to)) {

                        /* Save new sequence
                         */
                        console.log('save new sequence');
                    }
                    /* Dragging from division to a team
                     */
                    else if (isDivisionList(from) && isTeamPlayerList(to)) {
                        toFeedback.labelActivate({text: 'Adding to team...'});

                        /* Execute db transaction
                         */
                        toId = to.parents('[data-test-team]').attr('data-test-team');
                        console.log('dragging player from division to team ' + toId);

                        /* If dragged onto a proxy element, remove the player drag helper
                         * after team list is respawned
                         */
                        if (isTeamPlayerListProxy(to)) {
                            console.log('hide helper');
                        }

                        fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                        toFeedback.labelTimeout({text: 'Added!', css: 'success'});
                    }
                    /* Dragging players between teams
                     */
                    else if (isTeamPlayerList(from) && isTeamPlayerList(to)) {
                        fromFeedback.labelActivate({text: 'Removing from team...'});
                        toFeedback.labelActivate({text: 'Adding to team...'});

                        /* Execute db transaction
                         */
                        fromId = from.parents('[data-test-team]').attr('data-test-team');
                        toId = to.parents('[data-test-team]').attr('data-test-team');
                        console.log('dragging between teams - from: ' + fromId + ', to: ' + toId);

                        /* If dragged onto a proxy element, remove the player drag helper
                         * after team list is respawned
                         */
                        if (isTeamPlayerListProxy(to)) {
                            console.log('hide helper');
                        }

                        fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                        toFeedback.labelTimeout({text: 'Added!', css: 'success'});
                    }
                    /* Remove a player from a team
                     */
                    else if (isTeamPlayerList(from) && isDivisionList(to)) {
                        fromFeedback.labelActivate({text: 'Removing from team...'});

                        /* Execute db transaction
                         */
                        fromId = from.parents('[data-test-team]').attr('data-test-team');
                        console.log('remove a player from team ' + fromId);

                        fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                    }
                },
                over: function(event, ui) {

                    /* Reapply filter settings to division player list
                     */
                    console.log('reapply filters');
                }
            });
        }

        /**
         * $element is a division's player list sortable
         *
         * @param element
         */
        function isDivisionList(element) {
            return hasAttr(element, 'data-test-division-player-list');
        }

        /**
         * $element is a team's player list sortable
         *
         * Dragging into the title of the team requires a check on this element:
         * data-test-team-player-list-proxy
         *
         * Functions equivalently to dragging into player list itself, but this
         * provides us with a way of determining where the player was dragged
         *
         * @param element
         */
        function isTeamPlayerList(element) {
            return (hasAttr(element, 'data-test-team-player-list') || hasAttr(element, 'data-test-team-player-list-proxy'));
        }

        /**
         * $element is a team's player list proxy (alternate drag destination)
         *
         * @param element
         * @returns {boolean}
         */
        function isTeamPlayerListProxy(element) {
            return hasAttr(element, 'data-test-team-player-list-proxy');
        }

        /**
         * Check whether a data attribute is set on an element
         * http://stackoverflow.com/questions/1318076/jquery-hasattr-checking-to-see-if-there-is-an-attribute-on-an-element
         *
         * @param element
         * @param attribute
         * @returns {boolean}
         */
        function hasAttr(element, attribute) {
            return (typeof element.attr(attribute) !== typeof undefined && element.attr(attribute) !== false);
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