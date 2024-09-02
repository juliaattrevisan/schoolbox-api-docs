/**
 * Applies javascript manipulators on a Ruleset element
 * ie. the element containing Rules
 *
 * Usage: $('#ruleset').ups()
 */
(function () {
    var fetchInProgress = false;

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.ups = function (options) {
        options = options || {};

        var config = init(options, $(this));

        /* When adding new Conditions, they have this pseudo-id suffix, e.g.
         * #condition-new-latestConditionInstance
         */
        var latestConditionInstance = 1;

        /* When adding new Effects, they have this pseudo-id suffix, e.g.
         * #effect-new-latestEffectInstance
         */
        var latestEffectInstance = 1;

        initRuleUpdate();
        initRulesetUpdate();

        initRuleDelete();
        initRuleCollapse();
        initRuleReordering();

        initConditionCreate();
        initConditionDelete();

        initEffectCreate();
        initEffectUserfieldChange();
        initEffectMappingTypeChange();
        initEffectDelete();
        initEffectUserFieldAvailability();
        initEffectMappingRows();
        initEffectCheckbox();
        initEffectCheckboxChange();

        initAutoExpand();
        initExpandAll();

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

                /* Condition Settings
                 */
                condition: {
                    type: {
                        general: 1,
                        userExists: 2,
                    },
                    delete: {
                        url: "/adminv2/ups/condition/delete",
                        confirm: "Are you sure you want to delete this Condition?",
                        fail: "Could not delete Condition",
                    },
                },

                /* Effect Settings
                 */
                effect: {
                    change: {
                        url: "/adminv2/ups/effect/change",
                    },
                    delete: {
                        url: "/adminv2/ups/effect/delete",
                        confirm: "Are you sure you want to delete this Effect?",
                        fail: "Could not delete Effect",
                    },
                    map: {
                        newRowUrl: "/adminv2/ups/effect/newMappedRow",

                        /* Focus on first input in new Mapping Rows when they spawn
                         * This is pretty annoying for the Dropdown subfragment,
                         * so the default is off
                         */
                        focus: false,
                    },
                },

                // Ruleset Settings
                ruleset: {
                    update: {
                        url: "/adminv2/ups/ruleset/update",
                    },
                },

                /* Rule Settings
                 */
                rule: {
                    update: {
                        url: "/adminv2/ups/ruleset/rule/update",
                    },
                    delete: {
                        url: "/adminv2/ups/rule/delete",
                        confirm: "Are you sure you want to delete this Rule?",
                        fail: "Could not delete Rule",
                    },
                },

                /* Server response when an ajax transaction was successful
                 */
                success: "1",

                /* Valid Input Types for Conditions and Effects
                 */
                validInputTypes: "input, select, textarea",

                /* Stub Effect Prefix
                 * Used when creating new Effects, to avoid stub records
                 */
                stubPrefix: "new",
            };

            var derived = {};

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Update a single Rule from Ruleset.
         *
         * This is to avoid 'max_input_vars' being exceeded.
         *
         * Enable all input fields for form submission, otherwise they
         * don't get transmitted; then immediately disable them.
         *
         * On return, re-enable fields
         */
        function initRuleUpdate() {
            $(config.element).on("click", "[data-ruleset-save-rule]", function (event) {
                event.preventDefault();

                var $submit = $(this);
                var $rule = $submit.closest("[data-rule]");
                var $label = $rule.find('[data-relation="response-message"]');

                $label.toastActivate({
                    text: "Saving...",
                });

                saveRules($rule)
                    .done(function (data, textStatus, jqXHR) {
                        $rule.find(config.validInputTypes).attr("disabled", false);
                        $rule.find("option").removeAttr("disabled");
                        $rule.find("option").not("[value]").prop("disabled", true);
                        $label.toastTimeout({
                            text: "Saved",
                            css: "success",
                        });
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        $label.toastTimeout({
                            text: "Error",
                            css: "alert",
                        });
                    });

                $rule.find(config.validInputTypes).attr("disabled", true);
            });
            refreshSaveRulesetButtonAvailability();
        }

        /**
         * Update entire Ruleset
         *
         * Enable all input fields for form submission, otherwise they
         * don't get transmitted; then immediately disable them lol
         */
        function initRulesetUpdate() {
            $(config.element).on("click", "[data-ruleset-save]", function (event) {
                event.preventDefault();

                var $rules = $(config.element).find("[data-rule]");
                var $label = $(config.element).find('[data-relation="response-message"]').last();

                $label.toastActivate({
                    text: "Saving...",
                });

                saveRules($rules)
                    .done(function (data, textStatus, jqXHR) {
                        $(config.element).find(config.validInputTypes).attr("disabled", false);
                        $(config.element).find("option").removeAttr("disabled");
                        $(config.element).find("option").not("[value]").prop("disabled", true);
                        $label.toastTimeout({
                            text: "Saved",
                            css: "success",
                        });
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        $label.toastTimeout({
                            text: "Error",
                            css: "alert",
                        });
                    });

                $(config.element).find(config.validInputTypes).attr("disabled", true);
            });
            refreshSaveRulesetButtonAvailability();
        }

        /**
         * Save config and each rule in parallel
         *
         * Multiple parallel AJAX posts, each less than 'max_input_vars'
         *
         * @param $rules
         * @return Promise
         */
        function saveRules($rules) {
            // Config + Custom Rules
            var $config = $("#rulesetId").add($("[data-ruleset-enabled] [name]")).add($("[data-custom-rule] [name]"));

            var configArr = $config.serializeArray();

            // Rule sequence
            $("[data-rule]").each(function (idx, elem) {
                configArr.push({ name: "sequence[]", value: $(elem).attr("data-rule") });
            });

            // Add Ruleset config
            var ajaxes = [
                $.retryPromise(function () {
                    return $.post(config.ruleset.update.url, configArr);
                }),
            ];

            // Add each Rule
            $rules.each(function (idx, elem) {
                var $rule = $(elem);
                $rule.find(config.validInputTypes).attr("disabled", false);
                $rule.find("option").removeAttr("disabled");

                var ruleData = $rule.find(config.validInputTypes).serializeArray();

                ajaxes.push(
                    $.retryPromise(function () {
                        return $.post(config.rule.update.url, ruleData, function (data, textStatus, jqXHR) {
                            $rule.find("[data-label-status]").remove();

                            // Update newly inserted conditions
                            for (var oldConditionId in data.condition) {
                                var newConditionId = data.condition[oldConditionId];
                                if (newConditionId != oldConditionId) {
                                    updateDOM($("#condition-" + oldConditionId), oldConditionId, newConditionId);
                                }
                            }

                            // Update newly inserted effects
                            for (var oldEffectId in data.effect) {
                                var newEffectId = data.effect[oldEffectId];
                                if (newEffectId != oldEffectId) {
                                    updateDOM($("#effect-" + oldEffectId), oldEffectId, newEffectId);
                                }
                            }
                        });
                    }),
                );
            });
            return $.when.apply(null, ajaxes);
        }

        /**
         * Update a condition's or effect's DOM attributes with newly assigned id
         *
         * For all sub-elements attributes, replace 'oldId' with 'newId'
         *
         * @param $elem DOM node to search below
         * @param oldId
         * @param newId
         */
        function updateDOM($elem, oldId, newId) {
            // We could either try to match specific elements, or apply a liberal replacement strategy.
            // We elect the latter to avoid having to update this list each time the template changes.
            $elem.add($elem.find("*")).each(function (idx, elem) {
                $.each(elem.attributes, function (i, attr) {
                    var name = attr.name;
                    var value = attr.value;
                    if (-1 !== value.indexOf(oldId)) {
                        $(elem).attr(name, value.replace(oldId, newId));
                    }
                });
            });
        }

        /**
         * Delete a Rule
         */
        function initRuleDelete() {
            $(config.element).on("click", "[data-rule-delete]", function (event) {
                event.preventDefault();

                var ruleId = $(this).attr("data-rule-delete");
                var label = $("#rule-" + ruleId + "-status");

                $(this).confirmDialog({
                    text: config.rule.delete.confirm,
                    confirmEvent: function () {
                        /* Activate indicator label
                         */
                        label.toastActivate();

                        /* Delete asynchronously if successful; ignore if unsuccessful
                         */
                        $.post(config.rule.delete.url, { ruleId: ruleId }, function (responseText) {
                            if (responseText == config.success) {
                                $("#rule-" + ruleId).remove();

                                /* Indicator label gets removed with the condition so we
                                 * don't need to bother showing animation
                                 */
                            } else {
                                /* Show that something failed
                                 */
                                label.toastTimeout({
                                    text: config.rule.delete.fail,
                                    css: "alert",
                                });
                            }
                            refreshSaveRulesetButtonAvailability();
                        });
                    },
                });
            });
        }

        /**
         * Allow Rules to be Collapsable
         */
        function initRuleCollapse() {
            $("[data-collapser]").collapsable();
        }

        /**
         * Allow Rule Reordering
         */
        function initRuleReordering() {
            $(config.element).find("[data-sortable]").sortable({
                handle: ".grip",
                axis: "y",
            });
        }

        /**
         * Add a new Condition
         */
        function initConditionCreate() {
            $(config.element).on("click", "[data-condition-create]", function (event) {
                event.preventDefault();

                /* The Rule into which the new Effect is injected
                 */
                var ruleId = $(this).attr("data-condition-create");
                var $emptyState = $("#rule-" + ruleId).find("[data-empty-state-conditions]");
                var ruleContainer = "#rule-" + ruleId + "-conditions";

                /* The url from which new Conditions are spawned
                 */
                var url = $(this).attr("href");

                $.post(url, function (responseText) {
                    var conditionId = config.stubPrefix + "-" + latestConditionInstance;
                    var conditionContainerId = "condition-" + conditionId;
                    var statusId = "condition-" + conditionId + "-status";

                    var instance = $(responseText);

                    /* Update ids to use temporary index integer
                     */
                    instance.attr("id", conditionContainerId);

                    $.each(instance.find(config.validInputTypes), function (index, element) {
                        $(element).attr("name", $(element).attr("name").replace(config.stubPrefix, conditionId));
                    });

                    /* Update other miscellaneous id-based data vars
                     */
                    var allDataVars = ["data-condition-delete"];

                    $.each(allDataVars, function (index, element) {
                        instance.find("[" + element + "]").attr(element, conditionId);
                    });

                    /* Make status indicator unique
                     */
                    $(instance)
                        .find("#condition-" + config.stubPrefix + "-status")
                        .attr("id", statusId);

                    /* New instance will not have a delete link
                     */
                    $(instance).find("[data-condition-delete]").attr("href", "");

                    /* Ram it back in
                     */
                    $(ruleContainer).append(instance);

                    /* Keep count
                     */
                    latestConditionInstance++;

                    // Hide the empty state
                    $emptyState.addClass("hide");

                    // Show the save button
                    $("#rule-" + ruleId)
                        .find("div[data-ruleset-save-rule-row]")
                        .removeClass("hide");
                });
            });
        }

        /**
         * Delete a Condition
         */
        function initConditionDelete() {
            $(config.element).on("click", "[data-condition-delete]", function (event) {
                event.preventDefault();

                var $rule = $(this).closest("[data-rule]");
                var conditionId = $(this).attr("data-condition-delete");
                var label = $("#condition-" + conditionId + "-status");

                $(this).confirmDialog({
                    text: config.condition.delete.confirm,
                    confirmEvent: function () {
                        /* Activate indicator label
                         */
                        label.toastActivate();

                        /* Unsaved Conditions can be removed without needing the indicator
                         * as these don't require backend confirmation
                         */
                        if (conditionId.match(config.stubPrefix)) {
                            $("#condition-" + conditionId).remove();

                            checkRemainingConditions($rule);
                        } else {
                            /* Delete asynchronously if successful; ignore if unsuccessful
                             */
                            $.post(config.condition.delete.url, { conditionId: conditionId }, function (responseText) {
                                if (responseText == config.success) {
                                    $("#condition-" + conditionId).remove();

                                    /* Indicator label gets removed with the condition so we
                                     * don't need to bother showing animation
                                     */

                                    checkRemainingConditions($rule);
                                } else {
                                    /* Show that something failed
                                     */
                                    label.toastTimeout({
                                        text: config.condition.delete.fail,
                                        css: "alert",
                                    });
                                }
                            });
                        }
                    },
                });
            });
        }

        /**
         * If no conditions remain for this $rule, show the empty state
         * @param $rule
         */
        function checkRemainingConditions($rule) {
            var $conditions = $rule.find("#rule-" + $rule.attr("data-rule") + "-conditions").find("[data-condition]");
            if (0 === $conditions.length) {
                $rule.find("[data-empty-state-conditions]").removeClass("hide");
            }
        }

        /**
         * If no effects remain for this $rule, show the empty state
         * @param $rule
         */
        function checkRemainingEffects($rule) {
            var $effects = $rule.find("#rule-" + $rule.attr("data-rule") + "-effects").find("[data-effect]");
            if (0 === $effects.length) {
                $rule.find("[data-empty-state-effects]").removeClass("hide");
            }
        }

        /**
         * Add a new Effect
         */
        function initEffectCreate() {
            $(config.element).on("click", "[data-effect-create]", function (event) {
                event.preventDefault();

                /* The Rule into which the new Effect is injected
                 */
                var ruleId = $(this).attr("data-effect-create");
                var $emptyState = $("#rule-" + ruleId).find("[data-empty-state-effects]");
                var ruleContainer = "#rule-" + ruleId + "-effects";

                /* The url from which new Effect are spawned
                 */
                var url = $(this).attr("href");

                $.post(url, function (responseText) {
                    var effectId = config.stubPrefix + "-" + latestEffectInstance;
                    var effectContainerId = "effect-" + effectId;
                    var statusId = "effect-" + effectId + "-status";

                    var instance = $(responseText);

                    /* Update ids to use temporary index integer
                     */
                    instance.attr("id", effectContainerId);

                    $.each(instance.find(config.validInputTypes), function (index, element) {
                        $(element).attr("name", $(element).attr("name").replace(config.stubPrefix, effectId));
                    });

                    /* Update other miscellaneous id-based data vars
                     */
                    var allDataVars = [
                        "data-effect-userfield",
                        "data-effect-mappingtype",
                        "data-effect-fragment",
                        "data-effect-delete",
                    ];

                    $.each(allDataVars, function (index, element) {
                        instance.find("[" + element + "]").attr(element, effectId);
                    });

                    /* Make status indicator unique
                     */
                    $(instance)
                        .find("#effect-" + config.stubPrefix + "-status")
                        .attr("id", statusId)
                        .attr("data-effect-fragment", statusId);

                    $(instance)
                        .find("#effect-" + config.stubPrefix + "-fragment-status")
                        .attr("id", statusId);

                    /* New instance will not have a delete link
                     */
                    $(instance).find("[data-effect-delete]").attr("href", "");

                    /* Ram it back in
                     */
                    $(ruleContainer).append(instance);

                    /* Keep count
                     */
                    latestEffectInstance++;

                    refreshUserFieldAvailability(ruleId);

                    // Hide the empty state
                    $emptyState.addClass("hide");

                    // Show the save button
                    $("#rule-" + ruleId)
                        .find("div[data-ruleset-save-rule-row]")
                        .removeClass("hide");
                });
            });
        }

        /**
         * Detect when the Schoolbox User Field is changed, forcing
         * people to select a Mapping Type
         */
        function initEffectUserfieldChange() {
            $(config.element).on("change", "[data-effect-userfield]", function (event) {
                var ruleId = $(this).parents("[data-rule]").attr("data-rule");
                var effectId = $(this).attr("data-effect-userfield");

                /* Reset Mapping Type
                 */
                $("[data-effect-mappingtype=" + effectId + "]")[0].selectedIndex = 0;

                /* Empty the Effect Fragment container
                 */
                $("[data-effect-fragment=" + effectId + "]").html("");

                /* Disable User Fields already in play
                 */
                refreshUserFieldAvailability(ruleId);
            });
        }

        /**
         * Detect when the mapping type changes
         */
        function initEffectMappingTypeChange() {
            $(config.element).on("change", "[data-effect-mappingtype]", function (event) {
                var ruleId = $(this).attr("data-effect-ruleid");
                var effectId = $(this).attr("data-effect-mappingtype");
                var userField = disabledVal($("[data-effect-userfield=" + effectId + "]"));
                var mappingType = $(this).val();

                /* ruleId won't exist for unsaved effects when changing mapping type
                 * so we store it in a data var and recycle its value
                 */
                var data = {
                    ruleId: ruleId,
                    effectId: effectId,
                    userField: userField,
                    mappingType: mappingType,
                };

                var label = $("#effect-" + effectId + "-fragment-status");

                label.toastActivate();

                /* Inject fragment into Effect container
                 */
                $.get(config.effect.change.url, data, function (responseText) {
                    $("[data-effect-fragment=" + effectId + "]").html(responseText);

                    label.toastTimeout({
                        text: "Ready",
                    });
                });
            });
        }

        /**
         * Delete a Condition
         */
        function initEffectDelete() {
            $(config.element).on("click", "[data-effect-delete]", function (event) {
                event.preventDefault();

                var $rule = $(this).closest("[data-rule]");
                var effectId = $(this).attr("data-effect-delete");
                var label = $("#effect-" + effectId + "-status");

                $(this).confirmDialog({
                    text: config.effect.delete.confirm,
                    confirmEvent: function () {
                        /* Activate indicator label
                         */
                        label.toastActivate();

                        /* Unsaved Effects can be removed without needing the indicator
                         * as these don't require backend confirmation
                         */
                        if (effectId.match(config.stubPrefix)) {
                            $("#effect-" + effectId).remove();

                            checkRemainingEffects($rule);
                        } else {
                            /* Delete asynchronously if successful; ignore if unsuccessful
                             */
                            $.post(config.effect.delete.url, { effectId: effectId }, function (responseText) {
                                if (responseText == config.success) {
                                    $("#effect-" + effectId).remove();

                                    checkRemainingEffects($rule);

                                    /* Indicator label gets removed with the condition so we
                                     * don't need to bother showing animation
                                     */
                                } else {
                                    /* Show that something failed
                                     */
                                    label.toastTimeout({
                                        text: config.effect.delete.fail,
                                        css: "alert",
                                    });
                                }
                            });
                        }
                    },
                });
            });
        }

        /**
         * Disable User Fields already in play
         * Otherwise enable them
         *
         * Each User Field can only be modified once per Rule
         */
        function refreshUserFieldAvailability(ruleId) {
            var allUserFields = $(config.element)
                .find("[data-rule=" + ruleId + "]")
                .find("[data-effect-userfield]");

            /* Find all the User Fields currently chozen
             */
            var used = [];

            /* jQuery can't return the .val() of the option
             * when the option has been disabled, even if it
             * is also selected - which makes our Disable
             * campaign a little more finicky than desired
             *
             * (A $(select).val() will return null when its
             * currently selected option is disabled)
             *
             * https://bugs.jquery.com/ticket/13097
             */
            $.each(allUserFields, function (index, element) {
                used.push(disabledVal(element));
            });

            /* Disable all usages of these fields everywhere
             * in this rule
             */
            $.each(allUserFields, function (index, select) {
                $.each($(select).find("option"), function (index, option) {
                    /* Ignore "Please Select"
                     */
                    if (option.hasAttribute("value")) {
                        if ($.inArray($(option).val(), used) > -1) {
                            $(option).attr("disabled", "disabled");
                        } else {
                            $(option).removeAttr("disabled");
                        }
                    }
                });
            });
        }

        /**
         * Show/hide save button
         *
         * Show save button if there is at least one rule present
         * That one rule can be either a custom rule, or normal rule
         */
        function refreshSaveRulesetButtonAvailability() {
            var numRules = $(config.element).find("[data-rule]").length;
            var numCustomRules = $(config.element).find("[data-custom-rule]").length;
            var numRulesTotal = numRules + numCustomRules;

            var saveButton = $(config.element).find("[data-ruleset-save]");
            var emptyRulesetAlert = $("#emptyRulesetAlert");

            if (numRulesTotal) {
                saveButton.show();

                if (numRules) {
                    hideElement(emptyRulesetAlert);
                } else {
                    showElement(emptyRulesetAlert);
                }
            } else {
                saveButton.hide();
                showElement(emptyRulesetAlert);
            }
        }

        /**
         * Show element
         *
         * @param element
         */
        function showElement(element) {
            $(element).removeClass("hide");
        }

        /**
         * Hide element
         *
         * @param element
         */
        function hideElement(element) {
            $(element).addClass("hide");
        }

        /**
         * Disable options upon startup
         */
        function initEffectUserFieldAvailability() {
            $.each($(config.element).find("[data-rule]"), function (index, element) {
                var ruleId = $(element).attr("data-rule");

                refreshUserFieldAvailability(ruleId);
            });
        }

        /**
         * Preselect checkboxes in a key-multivalue context
         */
        function initEffectCheckbox() {
            $.each($(config.element).find("[data-mapping-row]"), function (index, element) {
                var container = $(element);
                var mapRowIdentifier = container.attr("data-mapping-row");

                var csv = $("#" + mapRowIdentifier + "-input").val();

                /* Split csv into their origina values
                 */
                var values = csv.split(",");

                /* Tick, where values were previously saved
                 */
                for (var i = 0; i < values.length; i++) {
                    container.find("input[value=" + values[i] + "]").prop("checked", true);
                }
            });
        }

        /**
         * Enable compilation of checkbox values into a csv upon
         * state change for aforementioned checkboxes. This allows
         * a key-multivalue (serialised) pair to exist
         */
        function initEffectCheckboxChange() {
            $(config.element).on("change", "[data-mapping-row] input[type=checkbox]", function (event) {
                var container = $(this).parents("[data-mapping-row]");
                var mapRowIdentifier = container.attr("data-mapping-row");

                /* csv of campusId values
                 */
                var values = [];

                /* Find all the ticked checkboxes in this div
                 */
                $.each(container.find("input[type=checkbox]:checked"), function (index, element) {
                    /* Assemble all ticked checkboxes' values into a csv
                     */
                    values.push($(element).val());
                });

                var csv = values.join(",");

                /* Set csv into hidden input field
                 */
                $("#" + mapRowIdentifier + "-input").val(csv);
            });
        }

        /**
         * An alternative to jQuery .val() which can't give you
         * the selected option for a <select> element when that
         * selected option is currently disabled
         */
        function disabledVal(element) {
            var selectedIndex = $(element).prop("selectedIndex") + 1;
            var value = $(element)
                .find("option:nth-child(" + selectedIndex + ")")
                .attr("value");

            return value;
        }

        /**
         * Every time a key or value input is blurred,
         * ensure there is an empty pair waiting to be
         * filled in - this saves us from having to make
         * a whole new set of add/delete controls
         */
        function initEffectMappingRows() {
            $(config.element).on("change keydown", "[data-map] input", function (event) {
                var row = $(this).parents("[data-map]");
                var rowContainer = row.parent();

                var ruleId = rowContainer.parents("[data-rule]").attr("data-rule");
                var effectId = rowContainer.parents("[data-effect-fragment]").attr("data-effect-fragment");
                var userProperty = disabledVal(
                    $("#effect-" + effectId).find("[data-effect-userfield=" + effectId + "]"),
                );

                /* Find out if all inputs are filled
                 */
                var allKeyInputs = rowContainer.find("input[data-key]");

                var numInputs = allKeyInputs.length;

                var numFilled = allKeyInputs.filter(function () {
                    return this.value.length > 0;
                }).length;

                /* If all the inputs are filled spawn a new row
                 * Bring focus to first input of new row
                 */
                if (numInputs == numFilled && !fetchInProgress) {
                    var data = {
                        ruleId: ruleId,
                        effectId: effectId,
                        userProperty: userProperty,
                    };

                    // block any new requests while we're retrieving a new row
                    fetchInProgress = true;

                    var label = $("#effect-" + effectId + "-status");
                    label.toastActivate();

                    $.get(config.effect.map.newRowUrl, data, function (responseText) {
                        rowContainer.append(responseText);

                        label.toastTimeout({
                            text: "Ready",
                        });
                    }).always(function () {
                        // ensure we don't accidentally infiniblock new row generation on the off-chance of a server error
                        fetchInProgress = false;
                    });
                }
            });
        }

        /**
         * Automatically expand a Rule when there's a hash
         * anchor in the url
         */
        function initAutoExpand() {
            var ruleId = window.location.hash.toString().substring(1, window.location.hash.length);

            var component = $(config.element).find("[data-collapsable=" + ruleId + "-contents]");
            var icon = $(config.element).find("[data-collapser=" + ruleId + "-contents]");

            $.fn.executeCollapsable(component, icon);
        }

        /**
         * Expand all Rules
         */
        function initExpandAll() {
            $(config.element)
                .find("[data-expand-all]")
                .click(function (event) {
                    var parentCollapsed = $(this).attr("data-collapse-state") === "collapsed";

                    if (parentCollapsed) {
                        $(this).attr("data-collapse-state", "uncollapsed");
                        parentCollapsed = false;
                    } else {
                        $(this).attr("data-collapse-state", "collapsed");
                        parentCollapsed = true;
                    }

                    $.fn.executeIconToggle($(this));

                    $.each($(config.element).find("[data-rule]"), function (index, element) {
                        var ruleId = "rule-" + $(element).attr("data-rule");

                        var component = $(element).find("[data-collapsable=" + ruleId + "-contents]");
                        var icon = $(element).find("[data-collapser=" + ruleId + "-contents]");

                        var childCollapsed = component.attr("data-collapse-state") === "collapsed";

                        if (childCollapsed != parentCollapsed) {
                            $.fn.executeCollapsable(component, icon);
                        }
                    });
                });
        }
    };
})(jQuery);
