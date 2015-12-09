<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Ruleset;

use Schoolbox\Role;
use Schoolbox\Sync\Grammar\Factory as GrammarFactory;
use Schoolbox\Sync\Grammar\FieldFactory;
use Schoolbox\Sync\Navigator\Navigator;
use Schoolbox\Sync\Navigator\Node;
use Schoolbox\Sync\Rule;
use Schoolbox\Sync\Condition;
use Schoolbox\Sync\Condition\Fragment\Factory as ConditionFragmentFactory;
use Schoolbox\Sync\Effect;
use Schoolbox\Sync\Effect\Fragment\Factory as EffectFragmentFactory;
use \Schoolbox\Sync\Effect\Type\Mapper as EffectTypeMapper;
use Schoolbox\Sync\Hydrator;

class Factory
{
    /**
     * Default state of Conditions when loading a Ruleset
     *
     * A meta field for various Condition-specific UI
     * so that when you load a Ruleset UI, all Conditions are
     * impossible to accidentally break. You must actively unlock
     * each Condition you want to edit.
     *
     * meta->disabled is the attribute on input, select, textarea elements
     * meta->lock->status is a flag for whether the Condition is locked (1 yes, 0 no)
     * meta->lock->icon is the action button icon- class to start with (opposite to its actual state)
     *
     * Should be set to "disabled", "1" and "unlocked" by default
     */
    private $meta = [
        'disabled' => 'disabled',
        'lock'     => [
            'status' => 1,
            'icon'   => 'unlocked',
        ]
    ];

    /**
     * Produce a twig template data based on Role Type
     *
     * @param string $rulesetId Role type - typically one of student|parent|staff
     *
     * @return array
     */
    public function produceTemplateData($rulesetId)
    {
        switch ($rulesetId) {

            /* Students
             */
            case Role::STUDENT:
                $allBreadcrumbs = new Navigator(
                    new Node('Home', '/adminv2/home', false),
                    new Node('User Provisioning System', '/adminv2/ups', false),
                    new Node('Student', '/adminv2/ups/student', true)
                );

                $allNavigation = new Navigator(
                    new Node('Student', '/adminv2/ups/student', true),
                    new Node('Parent', '/adminv2/ups/parent', false),
                    new Node('Staff', '/adminv2/ups/staff', false)
                );

                break;

            /* Parents
             */
            case Role::PARENT:
                $allBreadcrumbs = new Navigator(
                    new Node('Home', '/adminv2/home', false),
                    new Node('User Provisioning System', '/adminv2/ups', false),
                    new Node('Parent', '/adminv2/ups/parent', true)
                );

                $allNavigation = new Navigator(
                    new Node('Student', '/adminv2/ups/student', false),
                    new Node('Parent', '/adminv2/ups/parent', true),
                    new Node('Staff', '/adminv2/ups/staff', false)
                );

                break;

            /* Staff
             */
            case Role::STAFF:
                $allBreadcrumbs = new Navigator(
                    new Node('Home', '/adminv2/home', false),
                    new Node('User Provisioning System', '/adminv2/ups', false),
                    new Node('Staff', '/adminv2/ups/staff', true)
                );

                $allNavigation = new Navigator(
                    new Node('Student', '/adminv2/ups/student', false),
                    new Node('Parent', '/adminv2/ups/parent', false),
                    new Node('Staff', '/adminv2/ups/staff', true)
                );

                break;

            /* Unknown
             */
            default:
                return [];
        }

        /* Load Ruleset
         */
        $ruleset = $this->findRuleset($rulesetId);

        /* Load NDS Fields to chooze from
         */
        $ffactory = new FieldFactory();

        /* Compile metadata about Conditions and Effects, for presentation
         */
        $metas = [];

        $ruleIds = Rule\Mapper::getInstance()->findIdsByColumnList([ 'role_type' => $rulesetId ]);

        foreach (Condition\Mapper::getInstance()->findIdsByColumnList([ 'rule_id' => $ruleIds ]) as $conditionId) {
            $metas['condition'][$conditionId] = $this->getMeta();
        }

        foreach (Effect\Mapper::getInstance()->findIdsByColumnList([ 'rule_id' => $ruleIds ]) as $effectId) {
            $metas['effect'][$effectId] = $this->getMeta();
        }

        /* Determine which Condition Fragments to load initially
         * It tells frontend which templates to use for Conditions.
         */
        $conditionFragmentFactory = new ConditionFragmentFactory();

        /* Which twig containers and fragments to load for this
         * Ruleset's Conditions
         */
        $allConditionStructures = [
            'fragments' => []
        ];

        /* Determine which Effect Fragments to load initially
         * It tells frontend which templates to use for Effects.
         */
        $effectFragmentFactory = new EffectFragmentFactory($rulesetId);

        /* Which twig containers and fragments to load for this
         * Ruleset's Effects
         */
        $allEffectStructures = [
            'containers' => [],
            'fragments' => [],
            'allUserField' => $effectFragmentFactory->getAllFieldsKeyValue(),
        ];

        foreach ($ruleset as $currentRule) {
            foreach ($currentRule->getConditions() as $currentCondition) {
                $fragment = $conditionFragmentFactory->produce();
                $subfragment = $conditionFragmentFactory->produceType($currentCondition->getConditionType());

                $allConditionStructures['fragments'][$currentCondition->getId()]['fragment'] = $fragment;
                $allConditionStructures['fragments'][$currentCondition->getId()]['subfragments']['fragment'] = $subfragment;
            }

            /* Skip Effect has a different container since it's a unique kind of Effect
             */
            foreach ($currentRule->getEffects() as $currentEffect) {
                $allEffectStructures['containers'][$currentEffect->getId()] = $currentEffect->getEffectType()->getTemplate();

                /* Figure out which fragment to use if this type isn't a Skip
                 * Skip Effects don't use fragments
                 */
                if (!$currentEffect->getEffectType()->isSkip()) {
                    $fragment = $effectFragmentFactory->produce($currentEffect->getEffectType()->getId(), $currentEffect->getUserProperty());
                    $subfragment = $effectFragmentFactory->produceType($currentEffect->getUserProperty());

                    $allEffectStructures['fragments'][$currentEffect->getId()]['fragment'] = $fragment;
                    $allEffectStructures['fragments'][$currentEffect->getId()]['subfragments']['fragment'] = $subfragment;
                }
            }
        }

        /* If it was one of the known cases, return a well-formed structure
         * Otherwise it would have returned empty array earlier
         */
        return [
            'allBreadcrumbs'         => $allBreadcrumbs->getAllNodes(),
            'allNavigation'          => $allNavigation->getAllNodes(),
            'rulesetId'              => $rulesetId,
            'ruleset'                => $this->produceRuleset($rulesetId),
            'allConditionStructures' => $allConditionStructures,
            'allEffectStructures'    => $allEffectStructures,
            'allNDSField'            => $ffactory->buildMap($rulesetId),
            'meta'                   => $metas
        ];
    }

    /**
     * Produce ruleset data based on Role Type
     *
     * @param string         $rulesetId      Role type - typically one of student|parent|staff
     * @param GrammarFactory $grammarFactory An optional grammar builder, for converting the ruleset to grammar rules.
     *                                       Required if the UPS is to be run on users.
     *
     * @return Ruleset
     */
    public function produceRuleset($rulesetId, GrammarFactory $grammarFactory = null)
    {
        $rules       = $this->findRuleset($rulesetId);
        $customRules = Hydrator\Custom\Mapper::getInstance()->findByColumnList(
            ['role_type' => $rulesetId],
            ['order' => ['sequence' => 'asc'],]
        );
        $config      = Config\Mapper::getInstance()->findById($rulesetId);
        if (isset($grammarFactory)) {
            return new Ruleset(
                $rules,
                $customRules,
                $config,
                $grammarFactory->buildFromRuleCollection($rulesetId, $rules)
            );
        } else {
            return new Ruleset($rules, $customRules, $config);
        }
    }

    /**
     * Load a Ruleset based on Role Type student|parent|staff
     *
     * @param string $rulesetId
     *
     * @return Rule\Model[]
     */
    private function findRuleset($rulesetId)
    {
        // TODO DI
        $ruleset = Rule\Mapper::getInstance()->findByColumnList([ 'role_type' => $rulesetId ], [
            'order' => [ 'sequence' => 'ASC' ]
        ]);

        /* Order Conditions and Effects chronologically
         */
        foreach ($ruleset as $currentRule) {
            $currentRule->getConditions()->sortBy('id', 'ASC');
            $currentRule->getEffects()->sortBy('id', 'ASC');
        }

        return $ruleset;
    }

    /* Getters/Setters
     */

    /**
     * Get meta
     *
     * @return array
     */
    private function getMeta()
    {
        return $this->meta;
    }
}
