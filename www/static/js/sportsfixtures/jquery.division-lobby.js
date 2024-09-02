(function () {
    /**
     * Every sortable is connected to every other sortable
     * This enables you to drag:
     *     within a sortable;
     *     between division and team sortables;
     *     between team and other team sortables
     *
     * @param options
     */
    $.fn.divisionLobby = function (options) {
        options = options || {};

        let config = init(options, $(this));

        initSortables();
        initPlayerFilter();
        initCollapsables();
        initAddPlayerToDivisionSelectize();
        addClickHandlersToDivisionPlayerButtons();
        addClickHandlersToTeamPlayerButtons();
        applyDivisionPlayerFilter();

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
            let defaults = {
                // A list of all the connected sortables
                // The division lobby and all its destination teams and all the destination teams' proxies
                element: element,

                // Show console.log output or not.
                debugging: false,
            };

            let derived = {};

            return $.extend(defaults, derived, options);
        }

        /**
         * Start up the sortable plugin
         *
         * copyHelper: http://stackoverflow.com/questions/6940390/how-do-i-duplicate-item-when-using-jquery-sortable
         */
        function initSortables() {
            let copyHelper;

            $(config.element)
                .sortable({
                    connectWith: $(config.element),
                    handle: ".grip",
                    helper: function (event, ui) {
                        copyHelper = ui.clone().insertAfter(ui);
                        return ui.clone();
                    },
                    receive: function (event, ui) {
                        copyHelper = null;
                    },
                    stop: function (event, ui) {
                        copyHelper && copyHelper.remove();

                        let player = ui.item; // The player element that was dragged.

                        // The sortables from which the player was dragged, and to which they were dropped.
                        let from = $(event.target);

                        // the sortable into which the player has been dropped (one of three possibilities).
                        let to = player.closest(
                            "[data-team-player-list], [data-team-player-list-proxy], [data-division-player-list]",
                        );

                        let fromId; // Id of the 'to' HTML container element.
                        let toId; // Id of the 'from' HTML container element.
                        let fromFeedback = findIndicator(from); // Indicator for 'from' events.
                        let toFeedback = findIndicator(to); // Indicator for 'to' events.

                        /* Reordering within the same sortable.
                         */
                        if (from.is(to)) {
                            saveSequenceAndUpdatePlayersList(from); // Update both 'from' and 'to' lists since they are the same.
                        } else if (isDivisionList(from) && isTeamPlayerList(to)) {
                            /* Drag from division to team.
                             */
                            toId = to.parents("[data-team]").attr("data-team");

                            if (config.debugging) {
                                console.log("dragging player from division to team " + toId);
                            }

                            toFeedback.toastActivate({ text: "Adding to team..." });

                            $.post(
                                to.data("addPlayersAction"),
                                [{ name: "userId[]", value: player.data("player") }],
                                null,
                                "json",
                            )
                                .done(function (response) {
                                    if (config.debugging) {
                                        console.log(
                                            "DB: adding " +
                                                player.data("player") +
                                                " to " +
                                                to.data("addPlayersAction"),
                                        );
                                    }

                                    updateDivisionPlayersList(response.divisionLobby); // Update 'from' list.
                                    saveSequenceAndUpdatePlayersList(to); // Update 'to' list.
                                    fromFeedback.toastTimeout({ text: "Removed from team", css: "success" });
                                    toFeedback.toastTimeout({ text: "Added to team", css: "success" });
                                })
                                .fail(function () {
                                    toFeedback.toastTimeout({ text: "Add failed", css: "alert" });
                                    player.remove();
                                });
                        } else if (isTeamPlayerList(from) && isDivisionList(to)) {
                            /* Drag from team to division.
                             */
                            fromId = from.parents("[data-team]").attr("data-team");

                            if (config.debugging) {
                                console.log("remove a player from team " + fromId);
                            }

                            // The division players list always contains all players.  Therefore, when we drop a player into
                            // the division players list, we now have two players in the list with the same ID.  The player
                            // we just dropped is in the desired sequence, but this just dropped player has slightly different
                            // HTML compared to the player native to the division players list.  So we must move the native
                            // player into the position of the just-dropped player, and get rid of the just-dropped player.
                            // We do this in three steps:
                            //  1. Find HTML element of the pre-existing matching hidden player.
                            //  2. Replace the just-dropped player element with the hidden player.
                            //  3. Reveal the hidden player.
                            let hiddenPlayer = $("[data-division-player-list]").find(
                                "[data-player=" + player.attr("data-player") + "]:hidden",
                            );
                            player.replaceWith(hiddenPlayer);
                            hiddenPlayer.show();

                            fromFeedback.toastActivate({ text: "Removing from team..." });

                            $.post(
                                from.data("removePlayersAction"),
                                [{ name: "userId[]", value: player.data("player") }],
                                null,
                                "json",
                            )
                                .done(function (response) {
                                    if (config.debugging) {
                                        console.log(
                                            "DB: removing " +
                                                player.data("player") +
                                                " from " +
                                                from.data("removePlayersAction"),
                                        );
                                    }

                                    updateTeamPlayersList($("[data-team=" + fromId + "]"), response); // Update 'from' list.
                                    saveSequenceAndUpdatePlayersList(to); // Update 'to' list.
                                    fromFeedback.toastTimeout({ text: "Removed from team", css: "success" });
                                })
                                .fail(function () {
                                    fromFeedback.toastTimeout({ text: "Remove failed", css: "alert" });
                                });
                        } else if (isTeamPlayerList(from) && isTeamPlayerList(to)) {
                            /* Drag from team to team
                             */
                            fromId = from.parents("[data-team]").attr("data-team");
                            toId = to.parents("[data-team]").attr("data-team");

                            if (config.debugging) {
                                console.log("dragging between teams - from: " + fromId + ", to: " + toId);
                            }

                            toFeedback.toastActivate({ text: "Moving between teams..." });

                            let fromTeamConfiguration;

                            $.when(
                                $.post(
                                    from.data("removePlayersAction"),
                                    [{ name: "userId[]", value: player.data("player") }],
                                    function (response) {
                                        fromTeamConfiguration = response;
                                    },
                                    "json",
                                ),

                                $.post(
                                    to.data("addPlayersAction"),
                                    [{ name: "userId[]", value: player.data("player") }],
                                    function (response) {
                                        // Do nothing.
                                    },
                                    "json",
                                ),
                            )
                                .done(function (response) {
                                    if (config.debugging) {
                                        console.log(
                                            "DB: removing " +
                                                player.data("player") +
                                                " from " +
                                                from.data("removePlayersAction"),
                                        );
                                        console.log(
                                            "DB: adding " +
                                                player.data("player") +
                                                " to " +
                                                to.data("addPlayersAction"),
                                        );
                                    }

                                    updateTeamPlayersList($("[data-team=" + fromId + "]"), fromTeamConfiguration); // Update 'from' list.
                                    saveSequenceAndUpdatePlayersList(to); // Update 'to' list.
                                    toFeedback.toastTimeout({ text: "Player moved to team", css: "success" });
                                })
                                .fail(function () {
                                    toFeedback.toastTimeout({ text: "Moving player failed", css: "alert" });
                                    player.remove();
                                });
                        }
                    },
                })
                .disableSelection();
        }

        /**
         * Save new order of players in $sortable
         *
         * @param sortable
         * @returns {*}
         */
        function saveSequenceAndUpdatePlayersList(sortable) {
            if (config.debugging) {
                if (isDivisionList(sortable)) {
                    console.log("Saving new sequence for division " + sortable.attr("data-division-player-list"));
                } else {
                    console.log("Saving new sequence for team " + sortable.parents("[data-team]").attr("data-team"));
                }
            }

            let data = sortable.find("[data-player]").map(function () {
                return { name: "userId[]", value: $(this).data("player") };
            });

            $.post(sortable.data("reorderPlayersAction"), data, null, "json").then(function (response) {
                if (config.debugging) {
                    console.log("DB: sequence updated for " + (isDivisionList(sortable) ? "division" : "team") + ".");
                }

                if (isDivisionList(sortable)) {
                    updateDivisionPlayersList(response.html);
                } else {
                    updateTeamPlayersList(sortable.parents("[data-team]"), response);

                    // If dragged onto a proxy element, remove the player drag helper after team list is respawned.
                    // This will happen when the first player is dropped into an empty team.
                    if (hasAttr(sortable, "data-team-player-list-proxy")) {
                        sortable
                            .parents("[data-team]")
                            .find("[data-team-player-list-proxy]")
                            .children("[data-player]")
                            .remove();
                    }
                }
            });
        }

        /**
         * Add click handlers to the buttons on each division player (within the 3-dot menu).
         */
        function addClickHandlersToDivisionPlayerButtons() {
            // Add click handler for 'Add player to team' button (inside 3-dot menu).
            $(config.element).on("click", "[data-add-team-player]", function (e) {
                e.preventDefault();
                let el = $(this);
                let feedback = findIndicator(el.parents("[data-division-player-list]"));
                feedback.toastActivate({ text: "Adding to team..." });

                $.post(el.attr("href"), [{ name: "userId[]", value: el.data("addTeamPlayer") }], null, "json")
                    .done(function (response) {
                        updateTeamPlayersList($("[data-team=" + el.attr("data-affected-team") + "]"), response);
                        updateDivisionPlayersList(response.divisionLobby);
                        feedback.toastTimeout({ text: "Added to team", css: "success" });
                    })
                    .fail(function () {
                        feedback.toastTimeout({ text: "Add failed", css: "alert" });
                    });
            });

            // Add click handler for 'Remove player from division' button (inside 3-dot menu).
            // This also removes player from all teams they are currently in.
            $(config.element).on("click", "[data-remove-player]", function (e) {
                e.preventDefault();

                $(this).confirmDialog({
                    text: "Are you sure you wish to remove this player from this division, and all teams within this division?",
                    confirmEvent: function (el) {
                        let feedback = findIndicator(el.parents("[data-division-player-list]"));
                        feedback.toastActivate({ text: "Removing from division..." });

                        $.post(el.attr("href"), [{ name: "userId[]", value: el.data("removePlayer") }], null, "json")
                            .done(function (response) {
                                // Remove player from Division List and from all teams in which they existed
                                let playerId = el.parents("[data-player]").attr("data-player");
                                $("[data-division-player-list], [data-team-player-list]")
                                    .find("[data-player=" + playerId + "]")
                                    .remove();

                                feedback.toastTimeout({ text: "Removed from division", css: "success" });

                                applyDivisionPlayerFilter();
                            })
                            .fail(function () {
                                feedback.toastTimeout({ text: "Remove failed", css: "alert" });
                            });
                    },
                });
            });
        }

        /**
         * Remove click handlers from the buttons on each division player (within the 3-dot menu).
         * @see addClickHandlersToDivisionPlayerButtons()
         */
        function removeClickHandlersFromDivisionPlayerButtons() {
            $(config.element).off("click", "[data-add-team-player]");
            $(config.element).off("click", "[data-remove-player]");
        }

        /**
         * Add click handlers to the buttons on each team player.
         * @see removeClickHandlersFromTeamPlayerButtons()
         */
        function addClickHandlersToTeamPlayerButtons() {
            // Add click handler for 'Change player positions' button.
            $(document).on("click", "[data-player-positions-user]", function () {
                // Set the form data with the team/user IDs
                $("#playerPositionName").html(
                    $(this).attr("data-player-positions-team-name") +
                        " - " +
                        $(this).attr("data-player-positions-name"),
                );
                $("#playerPositionUserId").val($(this).attr("data-player-positions-user"));
                $("#playerPositionTeamId").val($(this).attr("data-player-positions-team"));

                // Get the players current positions and split into array
                let positions = $(this).attr("data-player-positions").split(",");

                $("[data-position]").each(function () {
                    // Deselect position
                    $(this).prop("checked", false);

                    // Iterate all position checkboxes and find out if we should check it
                    for (let i = 0; i < positions.length; i++) {
                        if ($(this).val() == positions[i]) {
                            // Player was in this position, check it
                            $(this).prop("checked", true);
                            break;
                        }
                    }
                });
            });

            // Add click handler for 'Remove player from team' button.
            $(config.element).on("click", "[data-remove-team-player]", function (e) {
                e.preventDefault();

                $(this).confirmDialog({
                    text: "Are you sure you wish to remove this player from this team?",
                    confirmEvent: function (el) {
                        let feedback = findIndicator(el.parents("[data-team-player-list]"));
                        feedback.toastActivate({ text: "Removing from team..." });

                        $.post(
                            el.attr("href"),
                            [{ name: "userId[]", value: el.data("removeTeamPlayer") }],
                            null,
                            "json",
                        )
                            .done(function (response) {
                                updateTeamPlayersList($("[data-team=" + el.attr("data-affected-team") + "]"), response);
                                updateDivisionPlayersList(response.divisionLobby);
                                feedback.toastTimeout({ text: "Removed from team", css: "success" });
                            })
                            .fail(function () {
                                feedback.toastTimeout({ text: "Remove failed", css: "alert" });
                            });
                    },
                });
            });
        }

        /**
         * Remove click handlers from the buttons on each team player.
         * @see addClickHandlersToTeamPlayerButtons()
         */
        function removeClickHandlersFromTeamPlayerButtons() {
            $(document).off("click", "[data-player-positions-user]");
            $(config.element).off("click", "[data-remove-team-player]");
        }

        /**
         * Replaces existing division lobby with new one
         *
         * This occurs every time a player moves into,
         * between, or out of a team
         *
         * @param newDivisionLobby
         */
        function updateDivisionPlayersList(newDivisionLobby) {
            let newHtml = $(newDivisionLobby).find("[data-division-player-list]").html();

            if (config.debugging) {
                let currDate = new Date();
                newHtml += "<div>Division list updated at " + currDate.toTimeString() + "</div>";
            }

            removeClickHandlersFromDivisionPlayerButtons(); // Remove click handlers from HTML elements about to be removed.
            $("[data-division-player-list]").html(newHtml);
            addClickHandlersToDivisionPlayerButtons(); // Attach click handlers to new HTML elements (note players may have been added or removed).

            applyDivisionPlayerFilter();
        }

        /**
         * Updates the Team's new player list
         * Updates the Team's player count
         */
        function updateTeamPlayersList(teamContainer, response) {
            let newHtml = response.html;

            if (config.debugging) {
                let currDate = new Date();
                newHtml += "<div>Team list updated at " + currDate.toTimeString() + "</div>";
            }

            removeClickHandlersFromTeamPlayerButtons(); // Remove click handlers from HTML elements about to be removed.
            teamContainer.find("[data-team-player-list]").html(newHtml);
            teamContainer.find("[data-player-count]").text(teamContainer.find("[data-player]").length);
            addClickHandlersToTeamPlayerButtons(); // Attach click handlers to new HTML elements (note players may have been added or removed).
        }

        /**
         * Apply the Player Filter setting to all the folks
         * in the division player list
         */
        function applyDivisionPlayerFilter() {
            let divisionPlayerList = $("[data-division-player-list]");

            switch (parseInt($("#player-filter").val())) {
                // Hide everyone, show unassigned
                case 1:
                    divisionPlayerList.find("[data-player]").hide();
                    divisionPlayerList.find("[data-assigned=0]").show();
                    break;

                // Hide everyone, show assigned
                case 2:
                    divisionPlayerList.find("[data-player]").hide();
                    divisionPlayerList.find("[data-assigned=1]").show();
                    break;

                // Show everyone
                case 0:
                default:
                    divisionPlayerList.find("[data-player]").show();
                    break;
            }
        }

        /**
         * Init event listener for player filter
         */
        function initPlayerFilter() {
            $("#player-filter").on("change", function () {
                applyDivisionPlayerFilter();
            });
        }

        /**
         * Every team is a collapsable
         */
        function initCollapsables() {
            $("[data-collapser]").collapsable();
        }

        /**
         * Selectize box for selecting one or more users
         */
        function initAddPlayerToDivisionSelectize() {
            $('[data-role="userSelector"]').userSelectizeSelector({
                multiple: true,
                ajax: {
                    data: { groupID: 0 },
                },
            });
        }

        /**
         * $element is a division's player list sortable
         *
         * @param element
         */
        function isDivisionList(element) {
            return hasAttr(element, "data-division-player-list");
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
            return hasAttr(element, "data-team-player-list") || hasAttr(element, "data-team-player-list-proxy");
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
            return typeof element.attr(attribute) !== typeof undefined && element.attr(attribute) !== false;
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
                return $("[data-division-indicator]");
            }

            return element.parents("[data-team]").find("[data-team-indicator]");
        }
    };
})(jQuery);
