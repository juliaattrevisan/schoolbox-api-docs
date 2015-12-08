/**
 * Applies javascript manipulators on a Ruleset element
 * ie. the element containing Rules
 *
 * Usage: $('#ruleset').ups()
 */
(function() {

    var fetchInProgress = false;

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.ups = function(options) {
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

        initRulesetUpdate();

        initRuleDelete();
        initRuleCollapse();
        initRuleReordering();

        initConditionCreate();
        initConditionModifyLock();
        initConditionDelete();

        initEffectCreate();
        initEffectModifyLock();
        initEffectUserfieldChange();
        initEffectMappingTypeChange();
        initEffectDelete();
        initEffectUserFieldAvailability();
        initEffectMappingRows();
        initEffectCheckbox();
        initEffectCheckboxChange();

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
                        userExists: 2
                    },
                    delete: {
                        url: '/adminv2/ups/condition/delete',
                        confirm: 'Are you sure you want to delete this Condition?',
                        fail: 'Could not delete Condition'
                    }
                },

                /* Effect Settings
                 */
                effect: {
                    change: {
                        url: '/adminv2/ups/effect/change'
                    },
                    delete: {
                        url: '/adminv2/ups/effect/delete',
                        confirm: 'Are you sure you want to delete this Effect?',
                        fail: 'Could not delete Effect'
                    },
                    map: {
                        newRowUrl: '/adminv2/ups/effect/newMappedRow',

                        /* Focus on first input in new Mapping Rows when they spawn
                         * This is pretty annoying for the Dropdown subfragment,
                         * so the default is off
                         */
                        focus: false
                    }
                },

                /* Rule Settings
                 */
                rule: {
                    delete: {
                        url: '/adminv2/ups/rule/delete',
                        confirm: 'Are you sure you want to delete this Rule?',
                        fail: 'Could not delete Rule'
                    }
                },

                /* data-locked value when a Condition/Effect is locked
                 * data-locked value when a Condition/Effect is unlocked
                 */
                locked: 1,
                unlocked: 0,

                /* Server response when an ajax transaction was successful
                 */
                success: '1',

                /* Valid Input Types for Conditions and Effects
                 */
                validInputTypes: 'input, select, textarea',

                /* Stub Effect Prefix
                 * Used when creating new Effects, to avoid stub records
                 */
                stubPrefix: 'new'
            }

            var derived = {
            }

            var merged = $.extend(defaults, derived, options);

            return merged;
        }

        /**
         * Update entire Ruleset
         *
         * Enable all input fields for form submission, otherwise they
         * don't get transmitted; then immediately disable them lol
         */
        function initRulesetUpdate() {
            $(config.element).on('click', 'input[type=submit]', function(event) {
                event.preventDefault();

                $(config.element).find(config.validInputTypes).attr('disabled', false);
                $(config.element).find('option').removeAttr('disabled');
                $(config.element).submit();
                $(config.element).find(config.validInputTypes).attr('disabled', true);
            });
        }

        /**
         * Delete a Rule
         */
        function initRuleDelete() {
            $(config.element).on('click', '[data-rule-delete]', function(event) {
                event.preventDefault();

                var ruleId = $(this).attr('data-rule-delete');
                var label = $('#rule-' + ruleId + '-status');

                $(this).confirmDialog({
                    text: config.rule.delete.confirm,
                    confirmEvent: function() {

                        /* Activate indicator label
                         */
                        label.labelActivate();

                        /* Delete asynchronously if successful; ignore if unsuccessful
                         */
                        $.post(config.rule.delete.url, { ruleId: ruleId }, function(responseText) {
                            if (responseText == config.success) {
                                $('#rule-' + ruleId).remove();

                                /* Indicator label gets removed with the condition so we
                                 * don't need to bother showing animation
                                 */
                            }
                            else {

                                /* Show that something failed
                                 */
                                label.labelTimeout({
                                    text: config.rule.delete.fail,
                                    css: 'alert'
                                });
                            }
                        });
                    }
                });
            });
        }

        /**
         * Allow Rules to be Collapsable
         */
        function initRuleCollapse() {
            $('[data-collapser]').collapsable();
        }

        /**
         * Allow Rule Reordering
         */
        function initRuleReordering() {
            $(config.element).find('[data-sortable]').sortable({
                handle: '.grip',
                axis: 'y'
            });
        }

        /**
         * Add a new Condition
         */
        function initConditionCreate() {
            $(config.element).on('click', '[data-condition-create]', function(event) {
                event.preventDefault();

                /* The Rule into which the new Effect is injected
                 */
                var ruleId = $(this).attr('data-condition-create');
                var ruleContainer = '#rule-' + ruleId + '-conditions';

                /* The url from which new Conditions are spawned
                 */
                var url = $(this).attr('href');

                $.post(url, function(responseText) {
                    var conditionId = config.stubPrefix + '-' + latestConditionInstance;
                    var conditionContainerId = 'condition-'+conditionId;
                    var statusId = 'condition-'+conditionId+'-status';

                    var instance = $(responseText);

                    /* Update ids to use temporary index integer
                     */
                    instance.attr('id', conditionContainerId);

                    $.each(instance.find(config.validInputTypes), function(index, element) {
                        $(element).attr('name', $(element).attr('name').replace(config.stubPrefix, conditionId));
                    });

                    /* Update other miscellaneous id-based data vars
                     */
                    var allDataVars = [
                        'data-condition-delete'
                    ];

                    $.each(allDataVars, function(index, element) {
                        instance.find('['+element+']').attr(element, conditionId);
                    });

                    /* Enable Modify Lock
                     */
                    instance.find('[data-condition-modify-lock]').attr('data-condition-modify-lock', conditionContainerId);

                    /* Make status indicator unique
                     */
                    $(instance)
                        .find('#condition-' + config.stubPrefix + '-status')
                        .attr('id', statusId);

                    /* New instance will not have a delete link
                     */
                    $(instance).find('[data-condition-delete]').attr('href', '');

                    /* Ram it back in
                     */
                    $(ruleContainer).append(instance);

                    /* Keep count
                     */
                    latestConditionInstance++;
                });
            });
        }

        /**
         * Condition Modify Lock
         *
         * Toggle input disability for modification
         * Works on both Conditions and Effects
         */
        function initConditionModifyLock() {
            $(config.element).on('click', '[data-condition-modify-lock]', function(event) {
                event.preventDefault();

                var id = '#' + $(this).attr('data-condition-modify-lock');

                if ($(id).attr('data-locked') == config.locked) {
                    $(id).find(config.validInputTypes).attr('disabled', false);
                    $(id).attr('data-locked', config.unlocked);

                    $(this).removeClass('icon-unlocked').addClass('icon-locked');
                }
                else {
                    $(id).find(config.validInputTypes).attr('disabled', true);
                    $(id).attr('data-locked', config.locked);

                    $(this).removeClass('icon-locked').addClass('icon-unlocked');
                }
            });
        }

        /**
         * Delete a Condition
         */
        function initConditionDelete() {
            $(config.element).on('click', '[data-condition-delete]', function(event) {
                event.preventDefault();

                var conditionId = $(this).attr('data-condition-delete');
                var label = $('#condition-' + conditionId + '-status');

                $(this).confirmDialog({
                    text: config.condition.delete.confirm,
                    confirmEvent: function() {

                        /* Activate indicator label
                         */
                        label.labelActivate();

                        /* Unsaved Conditions can be removed without needing the indicator
                         * as these don't require backend confirmation
                         */
                        if (conditionId.match(config.stubPrefix)) {
                            $('#condition-' + conditionId).remove();
                        }
                        /* Delete asynchronously if successful; ignore if unsuccessful
                         */
                        else {
                            $.post(config.condition.delete.url, { conditionId: conditionId }, function(responseText) {
                                if (responseText == config.success) {
                                    $('#condition-' + conditionId).remove();

                                    /* Indicator label gets removed with the condition so we
                                     * don't need to bother showing animation
                                     */
                                }
                                else {

                                    /* Show that something failed
                                     */
                                    label.labelTimeout({
                                        text: config.condition.delete.fail,
                                        css: 'alert'
                                    });
                                }
                            });
                        }
                    }
                });
            });
        }

        /**
         * Add a new Effect
         */
        function initEffectCreate() {
            $(config.element).on('click', '[data-effect-create]', function(event) {
                event.preventDefault();

                /* The Rule into which the new Effect is injected
                 */
                var ruleId = $(this).attr('data-effect-create');
                var ruleContainer = '#rule-' + ruleId + '-effects';

                /* The url from which new Effect are spawned
                 */
                var url = $(this).attr('href');

                $.post(url, function(responseText) {
                    var effectId = config.stubPrefix + '-' + latestEffectInstance;
                    var effectContainerId = 'effect-'+effectId;
                    var statusId = 'effect-'+effectId+'-status';

                    var instance = $(responseText);

                    /* Update ids to use temporary index integer
                     */
                    instance.attr('id', effectContainerId);

                    $.each(instance.find(config.validInputTypes), function(index, element) {
                        $(element).attr('name', $(element).attr('name').replace(config.stubPrefix, effectId));
                    });

                    /* Update other miscellaneous id-based data vars
                     */
                    var allDataVars = [
                        'data-effect-userfield',
                        'data-effect-mappingtype',
                        'data-effect-fragment',
                        'data-effect-delete'
                    ];

                    $.each(allDataVars, function(index, element) {
                        instance.find('['+element+']').attr(element, effectId);
                    });

                    /* Enable Modify Lock
                     */
                    instance.find('[data-effect-modify-lock]').attr('data-effect-modify-lock', effectContainerId);

                    /* Make status indicator unique
                     */
                    $(instance)
                        .find('#effect-' + config.stubPrefix + '-status')
                        .attr('id', statusId)
                        .attr('data-effect-fragment', statusId);

                    $(instance)
                        .find('#effect-' + config.stubPrefix + '-fragment-status')
                        .attr('id', statusId);

                    /* New instance will not have a delete link
                     */
                    $(instance).find('[data-effect-delete]').attr('href', '');

                    /* Ram it back in
                     */
                    $(ruleContainer).append(instance);

                    /* Keep count
                     */
                    latestEffectInstance++;

                    refreshUserFieldAvailability(ruleId);
                });
            });
        }

        /**
         * Effect Modify Lock
         *
         * Toggle input disability for modification
         * Works on both Conditions and Effects
         */
        function initEffectModifyLock() {
            $(config.element).on('click', '[data-effect-modify-lock]', function(event) {
                event.preventDefault();

                var id = '#' + $(this).attr('data-effect-modify-lock');

                if ($(id).attr('data-locked') == config.locked) {
                    $(id).find(config.validInputTypes).attr('disabled', false);
                    $(id).attr('data-locked', config.unlocked);

                    $(this).removeClass('icon-unlocked').addClass('icon-locked');
                }
                else {
                    $(id).find(config.validInputTypes).attr('disabled', true);
                    $(id).attr('data-locked', config.locked);

                    $(this).removeClass('icon-locked').addClass('icon-unlocked');
                }
            });
        }

        /**
         * Detect when the Schoolbox User Field is changed, forcing
         * people to select a Mapping Type
         */
        function initEffectUserfieldChange() {
            $(config.element).on('change', '[data-effect-userfield]', function(event) {
                var ruleId = $(this).parents('[data-rule]').attr('data-rule');
                var effectId = $(this).attr('data-effect-userfield');

                /* Reset Mapping Type
                 */
                $('[data-effect-mappingtype='+effectId+']').val(-1);

                /* Empty the Effect Fragment container
                 */
                $('[data-effect-fragment='+effectId+']').html('');

                /* Disable User Fields already in play
                 */
                refreshUserFieldAvailability(ruleId);
            });
        }

        /**
         * Detect when the mapping type changes
         */
        function initEffectMappingTypeChange() {
            $(config.element).on('change', '[data-effect-mappingtype]', function(event) {
                var ruleId = $(this).attr('data-effect-ruleid');
                var effectId = $(this).attr('data-effect-mappingtype');
                var userField = disabledVal($('[data-effect-userfield='+effectId+']'));
                var mappingType = $(this).val();

                /* ruleId won't exist for unsaved effects when changing mapping type
                 * so we store it in a data var and recycle its value
                 */
                var data = {
                    ruleId: ruleId,
                    effectId: effectId,
                    userField: userField,
                    mappingType: mappingType
                }

                var label = $('#effect-' + effectId + '-fragment-status');

                label.labelActivate();

                /* Inject fragment into Effect container
                 */
                $.get(config.effect.change.url, data, function(responseText) {
                    $('[data-effect-fragment='+effectId+']').html(responseText);

                    label.labelTimeout({
                        text: 'Ready',
                    });
                });
            });
        }

        /**
         * Delete a Condition
         */
        function initEffectDelete() {
            $(config.element).on('click', '[data-effect-delete]', function(event) {
                event.preventDefault();

                var effectId = $(this).attr('data-effect-delete');
                var label = $('#effect-' + effectId + '-status');

                $(this).confirmDialog({
                    text: config.effect.delete.confirm,
                    confirmEvent: function() {

                        /* Activate indicator label
                         */
                        label.labelActivate();

                        /* Unsaved Effects can be removed without needing the indicator
                         * as these don't require backend confirmation
                         */
                        if (effectId.match(config.stubPrefix)) {
                            $('#effect-' + effectId).remove();
                        }
                        /* Delete asynchronously if successful; ignore if unsuccessful
                         */
                        else {
                            $.post(config.effect.delete.url, { effectId: effectId }, function(responseText) {
                                if (responseText == config.success) {
                                    $('#effect-' + effectId).remove();

                                    /* Indicator label gets removed with the condition so we
                                     * don't need to bother showing animation
                                     */
                                }
                                else {

                                    /* Show that something failed
                                     */
                                    label.labelTimeout({
                                        text: config.effect.delete.fail,
                                        css: 'alert'
                                    });
                                }
                            });
                        }
                    }
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
            var allUserFields = $(config.element).find('[data-rule='+ruleId+']').find('[data-effect-userfield]');

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
            $.each(allUserFields, function(index, element) {
                used.push(disabledVal(element));
            });

            /* Disable all usages of these fields everywhere
             * in this rule
             */
            $.each(allUserFields, function(index, select) {
                $.each($(select).find('option'), function(index, option) {

                    /* Ignore "Please Select"
                     */
                    if ($(option).val() != -1) {
                        if ($.inArray($(option).val(), used) > -1) {
                            $(option).attr('disabled', 'disabled');
                        }
                        else {
                            $(option).removeAttr('disabled');
                        }
                    }
                });
            });
        }

        /**
         * Disable options upon startup
         */
        function initEffectUserFieldAvailability() {
            $.each($(config.element).find('[data-rule]'), function(index, element) {
                var ruleId = $(element).attr('data-rule');

                refreshUserFieldAvailability(ruleId);
            });
        }

        /**
         * Preselect checkboxes in a key-multivalue context
         */
        function initEffectCheckbox() {
            $.each($(config.element).find('[data-mapping-row]'), function(index, element) {
                var container = $(element);
                var mapRowIdentifier = container.attr('data-mapping-row');

                var csv = $('#mapping-row-input-' + mapRowIdentifier).val();

                /* Split csv into their origina values
                 */
                var values = csv.split(',');

                /* Tick, where values were previously saved
                 */
                for (var i = 0; i < values.length; i++) {
                    container.find('input[value='+values[i]+']').prop('checked', true);
                }
            });
        }

        /**
         * Enable compilation of checkbox values into a csv upon
         * state change for aforementioned checkboxes. This allows
         * a key-multivalue (serialised) pair to exist
         */
        function initEffectCheckboxChange() {
            $(config.element).on('change', '[data-mapping-row] input[type=checkbox]', function(event) {
                var container = $(this).parents('[data-mapping-row]');
                var mapRowIdentifier = container.attr('data-mapping-row');

                /* csv of campusId values
                 */
                var values = [];

                /* Find all the ticked checkboxes in this div
                 */
                $.each(container.find('input[type=checkbox]:checked'), function(index, element) {

                    /* Assemble all ticked checkboxes' values into a csv
                     */
                    values.push($(element).val());
                });

                var csv = values.join(',');

                /* Set csv into hidden input field
                 */
                $('#mapping-row-input-' + mapRowIdentifier).val(csv);
            });
        }

        /**
         * An alternative to jQuery .val() which can't give you
         * the selected option for a <select> element when that
         * selected option is currently disabled
         */
        function disabledVal(element) {
            var selectedIndex = ($(element).prop("selectedIndex")+1);
            var value = $(element).find('option:nth-child(' + selectedIndex + ')').attr('value');

            return value;
        }

        /**
         * Every time a key or value input is blurred,
         * ensure there is an empty pair waiting to be
         * filled in - this saves us from having to make
         * a whole new set of add/delete controls
         */
        function initEffectMappingRows() {
            $(config.element).on('change keydown', '[data-map] input', function(event) {
                var row = $(this).parents('[data-map]');
                var rowContainer = row.parent();

                var ruleId = rowContainer.parents('[data-rule]').attr('data-rule');
                var effectId = rowContainer.parents('[data-effect-fragment]').attr('data-effect-fragment');
                var userProperty = disabledVal($('#effect-' + effectId).find('[data-effect-userfield='+effectId+']'));

                /* Find out if all inputs are filled
                 */
                var allKeyInputs = rowContainer.find('input[data-key]');

                var numInputs = allKeyInputs.length;

                var numFilled = allKeyInputs.filter(function () {
                    return this.value.length > 0
                }).length;

                /* If all the inputs are filled spawn a new row
                 * Bring focus to first input of new row
                 */
                if (numInputs == numFilled && !fetchInProgress) {
                    var data = {
                        ruleId: ruleId,
                        effectId: effectId,
                        userProperty: userProperty
                    };

                    // block any new requests while we're retrieving a new row
                    fetchInProgress = true;

                    var label = $('#effect-' + effectId + '-status');
                    label.labelActivate();

                    $.get(config.effect.map.newRowUrl, data, function(responseText) {
                        rowContainer.append(responseText);

                        label.labelTimeout({
                            text: 'Ready'
                        });
                    }).always(function () {
                        // ensure we don't accidentally infiniblock new row generation on the off-chance of a server error
                        fetchInProgress = false;
                    });
                }
            });
        }
    }
}(jQuery));
