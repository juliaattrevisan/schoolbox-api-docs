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
        initPlayerFilter();

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
                 * The division lobby and all its destination teams and all the destination teams' proxies
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
            var copyHelper;

            $(config.element).sortable({
                connectWith: $(config.element),
                handle: '.grip',
                helper: function(e,li) {
                    copyHelper = li.clone().insertAfter(li);

                    return li.clone();
                },
                receive: function(event, ui) {
                    copyHelper = null;
                },
                stop: function(event, ui) {
                    copyHelper && copyHelper.remove();

                    var player = ui.item;
                    var from = $(event.target);
                    var to = player.closest('[data-test-team-player-list], [data-test-team-player-list-proxy]');

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

                        /* TODO: Execute db transaction (debugging)
                         */
                        toId = to.parents('[data-test-team]').attr('data-test-team');
                        console.log('dragging player from division to team ' + toId);
                        console.log($('[data-test-team='+toId+']'));

                        /* TODO: Refresh player list
                         */
                        $('[data-test-team='+toId+']').find('[data-test-team-player-list]').html('<li>Refresh with contents from db</li>');

                        /* If dragged onto a proxy element, remove the player drag helper
                         * after team list is respawned
                         */
                        if (isTeamPlayerListProxy(to)) {
                            removeProxyHelperClone(to);
                        }

                        /* Reapply filter settings to division player list
                         */
                        reapplyPlayerFilter();

                        fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                        toFeedback.labelTimeout({text: 'Added!', css: 'success'});
                    }
                    /* Dragging players between teams
                     */
                    else if (isTeamPlayerList(from) && isTeamPlayerList(to)) {
                        fromFeedback.labelActivate({text: 'Removing from team...'});
                        toFeedback.labelActivate({text: 'Adding to team...'});

                        /* TODO: Execute db transaction (debugging)
                         */
                        fromId = from.parents('[data-test-team]').attr('data-test-team');
                        toId = to.parents('[data-test-team]').attr('data-test-team');
                        console.log('dragging between teams - from: ' + fromId + ', to: ' + toId);

                        /* TODO: Refresh player list
                         */
                        $('[data-test-team='+fromId+']').find('[data-test-team-player-list]').html('<li>Refresh with contents from db</li>');
                        $('[data-test-team='+toId+']').find('[data-test-team-player-list]').html('<li>Refresh with contents from db</li>');

                        /* If dragged onto a proxy element, remove the player drag helper
                         * after team list is respawned
                         */
                        if (isTeamPlayerListProxy(to)) {
                            removeProxyHelperClone(to);
                        }

                        /* Reapply filter settings to division player list
                         */
                        reapplyPlayerFilter();

                        fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                        toFeedback.labelTimeout({text: 'Added!', css: 'success'});
                    }
                    /* Remove a player from a team
                     */
                    else if (isTeamPlayerList(from) && isDivisionList(to)) {
                        fromFeedback.labelActivate({text: 'Removing from team...'});

                        /* TODO: Execute db transaction (debugging)
                         */
                        fromId = from.parents('[data-test-team]').attr('data-test-team');
                        console.log('remove a player from team ' + fromId);

                        /* TODO: Refresh player list
                         */
                        $('[data-test-team='+fromId+']').find('[data-test-team-player-list]').html('<li>Refresh with contents from db</li>');

                        /* Reapply filter settings to division player list
                         */
                        reapplyPlayerFilter();

                        fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                    }
                    else {
                        //console.log(from, to);
                    }
                }
            });
        }

        /**
         * Init event listener for player filter
         */
        function initPlayerFilter() {
            $('#player-filter').on('change', function() {
                reapplyPlayerFilter();
            });
        }

        /**
         * Reapply the Player Filter setting to all the folks
         * in the division player list
         */
        function reapplyPlayerFilter() {
            var divisionPlayerList = $('[data-test-division-player-list]');

            switch (parseInt($('#player-filter').val())) {

                /* Hide everyone, show Unassigned
                 */
                case 1:
                    divisionPlayerList.find('[data-player]').hide();
                    divisionPlayerList.find('[data-assigned=0]').show();
                    break;

                /* Hide everyone, show Assigned
                 */
                case 2:
                    divisionPlayerList.find('[data-player]').hide();
                    divisionPlayerList.find('[data-assigned=1]').show();
                    break;

                /* Show Everyone
                 */
                case 0:
                default:
                    divisionPlayerList.find('[data-player]').show();
                    break;
            }
        }

        /**
         * If you dragged a player on the team title (player list proxy) it'll stay there
         * in a weird place until you manually remove them
         *
         * @param to
         */
        function removeProxyHelperClone(to) {
            to.parents('[data-test-team]').find('[data-test-team-player-list-proxy]').find('[data-player]').remove();
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