/**
 * Applies javascript manipulators on a Ruleset element
 * ie. the element containing Rules
 *
 * Usage: $('#ruleset').sync()
 */
(function() {

    /**
     * Plugin
     *
     * @param options
     */
    $.fn.sync = function(options) {
        options = options || {};

        var config = init(options, $(this));

        initRulesetUpdate();

        initRuleDelete();
        initRuleCollapse();

        initConditionCreate();
        initConditionModifyLock();
        initConditionDelete();

        initEffectCreate();
        initEffectModifyLock();
        initEffectMappingTypeChange();

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
                        url: '/adminv2/sync/condition/delete',
                        confirm: 'Are you sure you want to delete this Condition?',
                        fail: 'Could not delete Condition'
                    }
                },

                /* Effect Settings
                 */
                effect: {
                    change: {
                        url: '/adminv2/sync/effect/change'
                    }
                },

                /* Rule Settings
                 */
                rule: {
                    delete: {
                        url: '/adminv2/sync/rule/delete',
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
                validInputTypes: 'input, select, textarea'
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
         * Add a new Condition
         */
        function initConditionCreate() {
            $(config.element).on('click', '[data-condition-create]', function(event) {
                event.preventDefault();

                /* The Rule into which the new Condition is injected
                 */
                var ruleId = '#rule-' + $(this).attr('data-condition-create') + '-conditions';

                /* The url from which new Conditions are spawned
                 */
                var url = $(this).attr('href');

                $.post(url, function(responseText) {
                    $(ruleId).append(responseText);
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

                        /* Delete asynchronously if successful; ignore if unsuccessful
                         */
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
                var ruleId = '#rule-' + $(this).attr('data-effect-create') + '-effects';

                /* The url from which new Effect are spawned
                 */
                var url = $(this).attr('href');

                $.post(url, function(responseText) {
                    $(ruleId).append(responseText);
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
         * Detect when the mapping type changes
         */
        function initEffectMappingTypeChange() {
            $(config.element).on('click', '[data-effect-mappingtype]', function(event) {
                $(this).on('change', function(event) {
                    var effectId = $(this).attr('data-effect-mappingtype');
                    var userField = $('[data-effect-userfield='+effectId+']').val();
                    var mappingType = $(this).val();

                    var data = {
                        effectId: effectId,
                        userField: userField,
                        mappingType: mappingType
                    }

                    $.get(config.effect.change.url, data, function(responseText) {
                        console.log(responseText);
                    });
                });
            });
        }
    }
}(jQuery));