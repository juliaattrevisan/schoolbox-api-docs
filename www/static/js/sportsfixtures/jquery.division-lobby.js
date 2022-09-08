(function() {

    /**
     * Every sortable is connected to every other sortable
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

        /* haha
         */
        initSortables();
        initPlayerFilter();
        initCollapsables();
        initAddPlayerToTeam();
        initRemovePlayerFromTeam();
        initRemovePlayerFromDivision();
        initAddPlayerToDivisionSelectize();
        initApplyPositionSelector();

        /* Apply default filter state
         */
        applyPlayerFilter();

        /* Glossary of data vars

         Dragon drop receiving elements
             data-division-player-list
             data-team-player-list
             data-team-player-list-proxy
                (behaves like data-team-player-list, but needs extra handling)

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
                debugging: false
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
                    var to = player.closest('[data-team-player-list], [data-team-player-list-proxy], [data-division-player-list]');

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
                        saveOrdering(from);
                    }
                    /* Dragging from division to a team
                     */
                    else if (isDivisionList(from) && isTeamPlayerList(to)) {
                        toId = to.parents('[data-team]').attr('data-team');

                        if (config.debugging) {
                            console.log('dragging player from division to team ' + toId);
                        }

                        /* Activate indicators
                         */
                        toFeedback.toastActivate({text: 'Adding to team...'});

                        /* Execute db transaction - ajax call here
                         */
                        $.post(to.data('addPlayersAction'), [{name: 'userId[]', value: player.data('player')}], null, "json")
                            .then(function(response) {

                                /* Save new team player order
                                 */
                                saveOrdering(to);

                                return response;
                            })
                            .then(function(response) {
                                if (config.debugging) {
                                    console.log('DB: adding ' + player.data('player') + ' to ' + to.data('addPlayersAction'));
                                }

                                /* If dragged onto a proxy element, remove the player drag helper
                                 * after team list is respawned
                                 */
                                if (isTeamPlayerListProxy(to)) {
                                    removeProxyHelperClone(to);
                                }

                                updateTeamMetadata($('[data-team='+toId+']'), response);
                                updateDivisionLobby(response.divisionLobby);
                                initApplyPositionSelector();
                            })
                            .done(function() {

                                /* Activate indicators
                                 */
                                fromFeedback.toastTimeout({text: 'Removed from team', css: 'success'});
                                toFeedback.toastTimeout({text: 'Added to team' , css: 'success'});

                                /* Reapply filter settings to division player list
                                 */
                                applyPlayerFilter();
                            })
                            .fail(function() {
                                toFeedback.toastTimeout({text: 'Add failed', css: 'alert'});
                                player.remove();
                            });
                    }
                    /* Dragging players between teams
                     */
                    else if (isTeamPlayerList(from) && isTeamPlayerList(to)) {
                        fromId = from.parents('[data-team]').attr('data-team');
                        toId = to.parents('[data-team]').attr('data-team');

                        if (config.debugging) {
                            console.log('dragging between teams - from: ' + fromId + ', to: ' + toId);
                        }

                        /* Activate indicators
                         */
                        toFeedback.toastActivate({text: 'Moving between teams...'});

                        /* Execute db transaction - ajax call here
                         */
                        var fromTeamConfiguration;
                        var toTeamConfiguration;

                        $.when(
                            $.post(from.data('removePlayersAction'), [{name: 'userId[]', value: player.data('player')}], function(response) {
                                fromTeamConfiguration = response;
                            }, "json"),

                            $.post(to.data('addPlayersAction'), [{name: 'userId[]', value: player.data('player')}], function(response) {
                                toTeamConfiguration = response;
                            }, "json")
                        ).then(function(response) {

                                /* Save new team player order
                                 */
                                saveOrdering(to);

                                return response;
                            })
                            .then(function(response) {
                                if (config.debugging) {
                                    console.log('DB: removing ' + player.data('player') + ' from ' + from.data('removePlayersAction'));
                                    console.log('DB: adding ' + player.data('player') + ' to ' + to.data('addPlayersAction'));
                                }

                                /* If dragged onto a proxy element, remove the player drag helper
                                 * after team list is respawned
                                 */
                                if (isTeamPlayerListProxy(to)) {
                                    removeProxyHelperClone(to);
                                }

                                updateTeamMetadata($('[data-team='+fromId+']'), fromTeamConfiguration);
                                updateTeamMetadata($('[data-team='+toId+']'), toTeamConfiguration);
                                updateDivisionLobby(toTeamConfiguration.divisionLobby);
                                initApplyPositionSelector();
                            })
                            .done(function() {

                                /* Activate indicators
                                 */
                                toFeedback.toastTimeout({text: 'Player moved to team', css: 'success'});

                                /* Reapply filter settings to division player list
                                 */
                                applyPlayerFilter();
                            })
                            .fail(function() {
                                toFeedback.toastTimeout({text: 'Moving player failed', css: 'alert'});
                                player.remove();
                            });
                    }
                    /* Remove a player from a team
                     */
                    else if (isTeamPlayerList(from) && isDivisionList(to)) {
                        fromId = from.parents('[data-team]').attr('data-team');

                        if (config.debugging) {
                            console.log('remove a player from team ' + fromId);
                        }

                        /* Unique case: remove the clone from the division list
                         */
                        $('[data-division-player-list]').find(player).remove();

                        /* Activate indicators
                         */
                        fromFeedback.toastActivate({text: 'Removing from team...'});

                        /* Execute db transaction - ajax call here
                         */
                        $.post(from.data('removePlayersAction'), [{name: 'userId[]', value: player.data('player')}], null, "json")
                            .then(function(response) {

                                /* Save new team player order
                                 */
                                saveOrdering(to);

                                return response;
                            })
                            .then(function(response) {
                                if (config.debugging) {
                                    console.log('DB: removing ' + player.data('player') + ' from ' + from.data('removePlayersAction'));
                                }

                                updateTeamMetadata($('[data-team='+fromId+']'), response);
                                updateDivisionLobby(response.divisionLobby);
                                initApplyPositionSelector();
                            })
                            .done(function() {

                                /* Activate indicators
                                 */
                                fromFeedback.toastTimeout({text: 'Removed from team', css: 'success'});

                                /* Reapply filter settings to division player list
                                 */
                                applyPlayerFilter();
                            })
                            .fail(function() {
                                fromFeedback.toastTimeout({text: 'Remove failed', css: 'alert'});
                            });
                    }
                }
            }).disableSelection();
        }

        /**
         * Replaces existing division lobby with new one
         *
         * This occurs every time a player moves into,
         * between, or out of a team
         *
         * @param newDivisionLobby
         */
        function updateDivisionLobby(newDivisionLobby) {
            $('[data-division-player-list]').html($(newDivisionLobby).find('[data-division-player-list]').html());
        }

        /**
         * Updates the Team's new player list
         * Updates the Team's player count
         */
        function updateTeamMetadata(teamContainer, response) {
            teamContainer.find('[data-team-player-list]').html($(response.html));
            teamContainer.find('[data-player-count]').text(teamContainer.find('[data-player]').length);
        }

        /**
         * Every team is a collapsable
         */
        function initCollapsables() {
            $('[data-collapser]').collapsable();
        }

        /**
         * Selectize box for selecting one or more users
         */
        function initAddPlayerToDivisionSelectize() {
            $('[data-role="userSelector"]')
                .userSelectizeSelector({
                    multiple: true,
                    ajax: {
                        data: {groupID: 0},
                    }
                });
        }

        /**
         * Add a player to a team via tot
         */
        function initAddPlayerToTeam() {
            $(config.element).on('click', '[data-add-team-player]', function(e) {
                e.preventDefault();
                var el       = $(this);

                var feedback = findIndicator(el.parents('[data-division-player-list]'));
                feedback.toastActivate({text: 'Adding to team...'});

                $.post(el.attr('href'), [{name: 'userId[]', value: el.data('addTeamPlayer')}], null, "json")
                    .done(function(response) {
                        updateTeamMetadata($('[data-team='+el.attr('data-affected-team')+']'), response);
                        updateDivisionLobby(response.divisionLobby);
                    })
                    .done(function() {
                        feedback.toastTimeout({text: 'Added to team', css: 'success'});

                        /* Reapply filter settings to division player list
                         */
                        applyPlayerFilter();
                    })
                    .fail(function() {
                        feedback.toastTimeout({text: 'Add failed', css: 'alert'});
                    });
            });
        }

        /**
         * Remove a player from a team via tot
         */
        function initRemovePlayerFromTeam() {
            $(config.element).on('click', '[data-remove-team-player]', function(e) {
                e.preventDefault();

                $(this).confirmDialog({
                    text: 'Are you sure you wish to remove this player from this team?',
                    confirmEvent: function(el) {
                        var feedback = findIndicator(el.parents('[data-team-player-list]'));
                        feedback.toastActivate({text: 'Removing from team...'});

                        $.post(el.attr('href'), [{name: 'userId[]', value: el.data('removeTeamPlayer')}], null, "json")
                            .done(function(response) {
                                updateTeamMetadata($('[data-team='+el.attr('data-affected-team')+']'), response);
                                updateDivisionLobby(response.divisionLobby);
                            })
                            .done(function() {
                                feedback.toastTimeout({text: 'Removed from team', css: 'success'});

                                /* Reapply filter settings to division player list
                                 */
                                applyPlayerFilter();
                            })
                            .fail(function() {
                                feedback.toastTimeout({text: 'Remove failed', css: 'alert'});
                            });
                    }
                });
            });
        }

        /**
         * Remove a player from division lobby via tot
         * Also removes this player from all teams they are currently in
         */
        function initRemovePlayerFromDivision() {
            $(config.element).on('click', '[data-remove-player]', function(e) {
                e.preventDefault();

                $(this).confirmDialog({
                    text: 'Are you sure you wish to remove this player from this division, and all teams within this division?',
                    confirmEvent: function(el) {
                        var feedback = findIndicator(el.parents('[data-division-player-list]'));
                        feedback.toastActivate({text: 'Removing from division...'});

                        $.post(el.attr('href'), [{name: 'userId[]', value: el.data('removePlayer')}], null, "json")
                            .done(function(response) {

                                /* Remove player from Division List
                                 * Remove player from all teams in which they existed
                                 */
                                var playerId = el.parents('[data-player]').attr('data-player');

                                $('[data-division-player-list], [data-team-player-list]').find('[data-player='+playerId+']').remove();
                            })
                            .done(function() {
                                feedback.toastTimeout({text: 'Removed from division', css: 'success'});

                                /* Reapply filter settings to division player list
                                 */
                                applyPlayerFilter();
                            })
                            .fail(function() {
                                feedback.toastTimeout({text: 'Remove failed', css: 'alert'});
                            });
                    }
                });
            });
        }

        /**
         * Save new order of players in $sortable
         *
         * @param sortable
         * @returns {*}
         */
        function saveOrdering(sortable) {
            if (config.debugging) {
                if (isDivisionList(sortable)) {
                    console.log('Saving new sequence for division ' + sortable.attr('data-division-player-list'));
                }
                else {
                    console.log('Saving new sequence for team ' + sortable.parents('[data-team]').attr('data-team'));
                }
            }

            var data = sortable.find('[data-player]').map(function() {
                return {name: 'userId[]', value: $(this).data('player')};
            });

            return $.post(sortable.data('reorderPlayersAction'), data, null, "json");
        }

        /**
         * When the player clicks the edit positions button
         */
        function initApplyPositionSelector() {
            $(document).on('click', '[data-player-positions-user]', function() {

                // Set the form data with the team/user IDs
                $('#playerPositionName').html($(this).attr('data-player-positions-team-name') + ' - ' + $(this).attr('data-player-positions-name'));
                $('#playerPositionUserId').val($(this).attr('data-player-positions-user'));
                $('#playerPositionTeamId').val($(this).attr('data-player-positions-team'));

                // Get the players current positions and split into array
                var positions = $(this).attr('data-player-positions').split(',');

                $('[data-position]').each(function () {
                    // Deselect position
                    $(this).prop('checked', false);

                    // Iterate all position checkboxes and find out if we should check it
                    for (var i = 0; i < positions.length; i++) {
                        if ($(this).val() == positions[i]) {
                            // Player was in this position, check it
                            $(this).prop('checked', true);
                            break;
                        }
                    }
                });
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
            var divisionPlayerList = $('[data-division-player-list]');

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
            to.parents('[data-team]').find('[data-team-player-list-proxy]').children('[data-player]').remove();
        }

        /**
         * $element is a division's player list sortable
         *
         * @param element
         */
        function isDivisionList(element) {
            return hasAttr(element, 'data-division-player-list');
        }

        /**
         * $element is a team's player list sortable
         *
         * Dragging into the title of the team requires a check on this element:
         * data-team-player-list-proxy
         *
         * Functions equivalently to dragging into player list itself, but this
         * provides us with a way of determining where the player was dragged
         *
         * @param element
         */
        function isTeamPlayerList(element) {
            return (hasAttr(element, 'data-team-player-list') || hasAttr(element, 'data-team-player-list-proxy'));
        }

        /**
         * $element is a team's player list proxy (alternate drag destination)
         *
         * @param element
         * @returns {boolean}
         */
        function isTeamPlayerListProxy(element) {
            return hasAttr(element, 'data-team-player-list-proxy');
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
                return $('[data-division-indicator]');
            }

            return element.parents('[data-team]').find('[data-team-indicator]');
        }
    }
}(jQuery));
