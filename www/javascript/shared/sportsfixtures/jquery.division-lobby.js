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

        /* Glossary of data vars

         Dragon drop receiving elements
             data-test-division-player-list
             data-test-team-player-list
             data-test-team-player-list-proxy
                (behaves like data-test-team-player-list, but needs extra handling)

         Using dragon drop to add/remove players from teams and/or divisions
         These contain routes to backend
             data-add-players-action
             data-remove-players-action

         Using tots to add/remove players from teams and/or divisions
             data-add-team-player
             data-remove-team-player
             data-remove-player
         */

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
                element: element,

                /* Show console.log output
                 */
                debugging: true
            };

            var derived = {
            };

            return $.extend(defaults, derived, options);
        }

        /**
         * Start up the sortable plugin
         *
         * copyHelper: http://stackoverflow.com/questions/6940390/how-do-i-duplicate-item-when-using-jquery-sortable
         */
        function initSortables() {
            var copyHelper;

            $(config.element).sortable({
                connectWith: $(config.element),
                handle: '.grip',
                helper: function(event, ui) {
                    copyHelper = ui.clone().insertAfter(ui);

                    return ui.clone();
                },
                receive: function(event, ui) {
                    copyHelper = null;
                },
                stop: function(event, ui) {
                    copyHelper && copyHelper.remove();

                    /* The player element that was dragged
                     */
                    var player = ui.item;

                    /* The sortable from which $player was dragged
                      */
                    var from = $(event.target);

                    /* The three possible destinations into which $player can drop
                     */
                    var to = player.closest('[data-test-team-player-list], [data-test-team-player-list-proxy], [data-test-division-player-list]');

                    /* Indicators
                     */
                    var fromFeedback = findIndicator(from);
                    var toFeedback = findIndicator(to);

                    /* For debugging only
                     */
                    if (config.debugging) {
                        var fromId;
                        var toId;
                    }

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
                        if (config.debugging) {
                            toId = to.parents('[data-test-team]').attr('data-test-team');
                            console.log('dragging player from division to team ' + toId);
                        }

                        /* Activate indicators
                         */
                        toFeedback.labelActivate({text: 'Adding to team...'});

                        /* Execute db transaction - ajax call here
                         */
                        $.post(to.data('addPlayersAction'), [{name: 'userId[]', value: player.data('player')}])
                            .then(function(response) {

                                /* TODO: Save new team player order
                                 * This is a secondary ajax call which doesn't really need to happen, but ok
                                 */
                                return response;
                            })
                            .then(function(response) {
                                if (config.debugging) {
                                    console.log('DB: adding ' + player.data('player') + ' to ' + to.data('addPlayersAction'));
                                }

                                /* TODO: Refresh player list
                                 */
                                $('[data-test-team='+toId+']').find('[data-test-team-player-list]').html('<li>Refresh with contents from db</li>');
                            })
                            .done(function() {

                                /* If dragged onto a proxy element, remove the player drag helper
                                 * after team list is respawned
                                 */
                                if (isTeamPlayerListProxy(to)) {
                                    removeProxyHelperClone(to);
                                }

                                /* Activate indicators
                                 */
                                fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                                toFeedback.labelTimeout({text: 'Added!', css: 'success'});

                                /* Reapply filter settings to division player list
                                 */
                                applyPlayerFilter();
                            });
                    }
                    /* Dragging players between teams
                     */
                    else if (isTeamPlayerList(from) && isTeamPlayerList(to)) {
                        if (config.debugging) {
                            fromId = from.parents('[data-test-team]').attr('data-test-team');
                            toId = to.parents('[data-test-team]').attr('data-test-team');
                            console.log('dragging between teams - from: ' + fromId + ', to: ' + toId);
                        }

                        /* Activate indicators
                         */
                        fromFeedback.labelActivate({text: 'Removing from team...'});
                        toFeedback.labelActivate({text: 'Adding to team...'});

                        /* Execute db transaction - ajax call here
                         */
                        var fromTeamConfiguration;
                        var toTeamConfiguration;

                        $.when(
                            $.post(from.data('removePlayersAction'), [{name: 'userId[]', value: player.data('player')}], function(response) {
                                fromTeamConfiguration = response;
                            }),

                            $.post(to.data('addPlayersAction'), [{name: 'userId[]', value: player.data('player')}], function(response) {
                                toTeamConfiguration = response;
                            })
                        ).then(function(response) {

                                /* TODO: Save new team player order
                                 * This is a secondary ajax call which doesn't really need to happen, but ok
                                 */
                                return response;
                            })
                            .then(function(response) {
                                if (config.debugging) {
                                    console.log('DB: removing ' + player.data('player') + ' from ' + from.data('removePlayersAction'));
                                    console.log('DB: adding ' + player.data('player') + ' to ' + to.data('addPlayersAction'));
                                }

                                /* TODO: Refresh player list
                                 */
                                $('[data-test-team='+fromId+']').find('[data-test-team-player-list]').html('<li>Refresh with contents from db</li>');
                                $('[data-test-team='+toId+']').find('[data-test-team-player-list]').html('<li>Refresh with contents from db</li>');
                            })
                            .done(function() {

                                /* If dragged onto a proxy element, remove the player drag helper
                                 * after team list is respawned
                                 */
                                if (isTeamPlayerListProxy(to)) {
                                    removeProxyHelperClone(to);
                                }

                                /* Activate indicators
                                 */
                                fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});
                                toFeedback.labelTimeout({text: 'Added!', css: 'success'});

                                /* Reapply filter settings to division player list
                                 */
                                applyPlayerFilter();
                            });
                    }
                    /* Remove a player from a team
                     */
                    else if (isTeamPlayerList(from) && isDivisionList(to)) {
                        if (config.debugging) {
                            fromId = from.parents('[data-test-team]').attr('data-test-team');
                            console.log('remove a player from team ' + fromId);
                        }

                        /* Unique case: remove the clone from the division list
                         */
                        $('[data-test-division-player-list]').find(player).remove();

                        /* Activate indicators
                         */
                        fromFeedback.labelActivate({text: 'Removing from team...'});

                        /* Execute db transaction - ajax call here
                         */
                        $.post(from.data('removePlayersAction'), [{name: 'userId[]', value: player.data('player')}])
                            .then(function(response) {

                                /* TODO: Save new team player order
                                 * This is a secondary ajax call which doesn't really need to happen, but ok
                                 */
                                return response;
                            })
                            .then(function(response) {
                                if (config.debugging) {
                                    console.log('DB: removing ' + player.data('player') + ' from ' + from.data('removePlayersAction'));
                                }

                                /* TODO: Refresh player list
                                 */
                                $('[data-test-team='+fromId+']').find('[data-test-team-player-list]').html('<li>Refresh with contents from db</li>');
                            })
                            .done(function() {

                                /* Activate indicators
                                 */
                                fromFeedback.labelTimeout({text: 'Removed!', css: 'success'});

                                /* Reapply filter settings to division player list
                                 */
                                applyPlayerFilter();
                            });
                    }
                }
            });
        }

        /**
         * Init event listener for player filter
         */
        function initPlayerFilter() {
            $('#player-filter').on('change', function() {
                applyPlayerFilter();
            });
        }

        /**
         * Apply the Player Filter setting to all the folks
         * in the division player list
         */
        function applyPlayerFilter() {
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