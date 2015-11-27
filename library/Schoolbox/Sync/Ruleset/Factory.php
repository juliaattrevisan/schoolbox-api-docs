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
use Schoolbox\Sync\Grammar\FieldFactory;
use Schoolbox\Sync\Navigator\Navigator;
use Schoolbox\Sync\Navigator\Node;
use Schoolbox\Sync\Rule;
use Schoolbox\Sync\Condition;
use Schoolbox\Sync\Effect;
use Schoolbox\Sync\Effect\AllUserField;

class Factory
{

    /**
     * Role type - typically one of student|parent|staff
     *
     * @var string
     */
    private $type = '';

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
     * Constructor
     *
     * @param $rulesetId
     */
    public function __construct($rulesetId)
    {
        $this->setType($rulesetId);
    }


    /**
     * Produce a twig template data based on Role Type
     *
     * @return array
     */
    public function produce()
    {
        switch ($this->getType()) {

            /* Students
             */
            case Role::STUDENT:
                $allBreadcrumbs = new Navigator(
                    new Node('Home', '/adminv2/home', false),
                    new Node('Sync (UPS)', '/adminv2/sync', false),
                    new Node('Student', '/adminv2/sync/student', true)
                );

                $allNavigation = new Navigator(
                    new Node('Student', '/adminv2/sync/student', true),
                    new Node('Parent', '/adminv2/sync/parent', false),
                    new Node('Staff', '/adminv2/sync/staff', false)
                );

                break;

            /* Parents
             */
            case Role::PARENT:
                $allBreadcrumbs = new Navigator(
                    new Node('Home', '/adminv2/home', false),
                    new Node('Sync (UPS)', '/adminv2/sync', false),
                    new Node('Parent', '/adminv2/sync/parent', true)
                );

                $allNavigation = new Navigator(
                    new Node('Student', '/adminv2/sync/student', false),
                    new Node('Parent', '/adminv2/sync/parent', true),
                    new Node('Staff', '/adminv2/sync/staff', false)
                );

                break;

            /* Staff
             */
            case Role::STAFF:
                $allBreadcrumbs = new Navigator(
                    new Node('Home', '/adminv2/home', false),
                    new Node('Sync (UPS)', '/adminv2/sync', false),
                    new Node('Staff', '/adminv2/sync/staff', true)
                );

                $allNavigation = new Navigator(
                    new Node('Student', '/adminv2/sync/student', false),
                    new Node('Parent', '/adminv2/sync/parent', false),
                    new Node('Staff', '/adminv2/sync/staff', true)
                );

                break;

            /* Unknown
             */
            default:
                return [];
        }

        /* Load Ruleset
         */
        $ruleset = $this->findRuleset();

        /* Load NDS Fields to chooze from
         */
        $ffactory = new FieldFactory();

        /* Compile metadata about Conditions and Effects, for presentation
         */
        $metas = [];

        $ruleIds = Rule\Mapper::getInstance()->findIdsByColumnList([ 'role_type' => $this->getType() ]);

        foreach (Condition\Mapper::getInstance()->findIdsByColumnList([ 'rule_id' => $ruleIds ]) as $conditionId) {
            $metas['condition'][$conditionId] = $this->getMeta();
        }

        foreach (Effect\Mapper::getInstance()->findIdsByColumnList([ 'rule_id' => $ruleIds ]) as $effectId) {
            $metas['effect'][$effectId] = $this->getMeta();
        }

        /* Determine which Effect Fragments to load initially
         * This is just some hardcoded values for now.
         * It tells frontend which templates to use for Effects.
         */
        $allUserField = new AllUserField();

        /* Which twig containers and fragments to load for this
         * Ruleset's Effects
         */
        $allEffectStructures = [
            'containers' => [],
            'fragments' => [],
            'allUserField' => $allUserField->getAllFields(),
            'allNDSField' => $ffactory->buildMap($this->getType()),
        ];

        /* Skip Effect has a different container since it's a unique kind of Effect
         */
        foreach ($ruleset as $currentRule) {
            foreach ($currentRule->getEffects() as $currentEffect) {
                if ($currentEffect->getEffectType()->getId() == AllUserField::EFFECT_TYPE_SKIP) {
                    $allEffectStructures['containers'][$currentEffect->getId()] = '@syncEffect/container-skip.twig';
                }
                else {
                    $allEffectStructures['containers'][$currentEffect->getId()] = '@syncEffect/container.twig';
                    $allEffectStructures['fragments'][$currentEffect->getId()] = $allUserField->getEffectFragmentTemplate($currentEffect->getEffectType()->getId(), $currentEffect->getUserProperty());
                }
            }
        }

        /* If it was one of the known cases, return a well-formed structure
         * Otherwise it would have returned empty array earlier
         */
        return [
            'allBreadcrumbs'      => $allBreadcrumbs->getAllNodes(),
            'allNavigation'       => $allNavigation->getAllNodes(),
            'rulesetId'           => $this->getType(),
            'ruleset'             => [ 'rules' => $ruleset ],
            'allEffectStructures' => $allEffectStructures,
            'meta'                => $metas
        ];
    }

    /**
     * Load a Ruleset based on Role Type student|parent|staff
     *
     * @return array
     */
    private function findRuleset()
    {
        // TODO DI
        return Rule\Mapper::getInstance()->findByColumnList([ 'role_type' => $this->getType() ], [
            'order' => [ 'sequence' => 'ASC' ]
        ]);
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


    /**
     * @param string $type
     */
    private function setType($type)
    {
        $this->type = $type;
    }


    /**
     * @return string
     */
    private function getType()
    {
        return $this->type;
    }
}