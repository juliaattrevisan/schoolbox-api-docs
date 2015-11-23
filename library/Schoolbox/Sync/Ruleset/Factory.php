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

use \Schoolbox\Sync\Navigator\Navigator;
use \Schoolbox\Sync\Navigator\Node;

class Factory {

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
        'lock' => [
            'status' => 1,
            'icon' => 'unlocked',
        ]
    ];

    /**
     * Constructor
     *
     * @param $rulesetId
     */
    public function __construct($rulesetId) {
        $this->setType($rulesetId);
    }

    /**
     * Produce a twig template data based on Role Type
     *
     * @return array
     */
    public function produce() {
        switch($this->getType()) {

            /* Students
             */
            case 'student':
                $allBreadcrumbs = new Navigator(
                    new Node('Home', '/adminv2', false),
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
            case 'parent':
                $allBreadcrumbs = new Navigator(
                    new Node('Home', '/adminv2', false),
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
            case 'staff':
                $allBreadcrumbs = new Navigator(
                    new Node('Home', '/adminv2', false),
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

        /* If it was one of the known cases, return a well-formed structure
         * Otherwise it would have returned empty array earlier
         */
        return [
            'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
            'allNavigation' => $allNavigation->getAllNodes(),
            'rulesetId' => $this->getType(),
            'ruleset' => $this->findRuleset($this->getType()),
        ];
    }

    /**
     * Load a Ruleset based on Role Type student|parent|staff
     *
     * TODO: Load from database
     *
     * @param $type
     * @return array
     */
    private function findRuleset($type) {

        /* TODO: Construct Ruleset from database
         * This is an example Ruleset structure
         */
        $ruleset = [
            'rules' => [
                [
                    'id' => 999,
                    'name' => 'The First Rule',
                    'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Proin tempus consequat leo, id tempor arcu dapibus sit amet. Maecenas at bibendum leo, quis vestibulum dolor.',
                    'conditions' => [
                        [
                            'condition' => [
                                'id' => 1231,
                                'type' => 1,
                                'rule_id' => 999,
                                'meta' => $this->getMeta()
                            ],
                            'selected' => [
                                'NDSField' => 3,
                                'matches' => 1,
                                'values' => "Mort\nBart\nGruber"
                            ]
                        ],
                        [
                            'condition' => [
                                'id' => 4458,
                                'type' => 2,
                                'rule_id' => 999,
                                'meta' => $this->getMeta()
                            ],
                            'selected' => [
                                'matches' => 1,
                            ]
                        ]
                    ],
                    'effects' => [
                    ]
                ],
                [
                    'id' => 1000,
                    'name' => 'The Second Rule',
                    'description' => 'Duis lobortis, augue eget luctus pulvinar, est mauris pellentesque eros, non bibendum diam urna a libero. Nam mattis nulla eu tincidunt vestibulum. Integer feugiat auctor massa ac egestas',
                    'conditions' => [
                        [
                            'condition' => [
                                'id' => 789,
                                'type' => 1,
                                'rule_id' => 1000,
                                'meta' => $this->getMeta()
                            ],
                            'selected' => [
                                'NDSField' => 1,
                                'matches' => 2,
                                'values' => "S-12344"
                            ]
                        ]
                    ],
                    'effects' => [
                    ]
                ]
            ]
        ];

        return $ruleset;
    }

    /* Getters/Setters
     */

    /**
     * Get meta
     *
     * @return array
     */
    private function getMeta() {
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