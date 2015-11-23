<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Ruleset;

use Alaress\Web\Controller\Base;
use \Schoolbox\Sync\Navigator\Navigator;
use \Schoolbox\Sync\Navigator\Node;

class Controller extends Base {

    /**
     * Render intro
     */
    public function intro() {

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2', false),
            new Node('Sync (UPS)', '/adminv2/sync', true)
        );

        /* Navigation
         */
        $allNavigation = new Navigator(
            new Node('Student', '/adminv2/sync/student', false),
            new Node('Parent', '/adminv2/sync/parent', false),
            new Node('Staff', '/adminv2/sync/staff', false)
        );

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/intro.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'allNavigation' => $allNavigation->getAllNodes(),
            ]
        ]);
    }

    /**
     * Ruleset view
     *
     * @param $params
     */
    public function ruleset($params) {
        switch ($params['rulesetId']) {
            case 'student': $this->student($params); break;
            case 'parent': $this->parent($params); break;
            case 'staff': $this->staff($params); break;
            default: break;
        }
    }

    /**
     * Student Test Method
     *
     * @param array $params
     */
    private function student($params) {

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2', false),
            new Node('Sync (UPS)', '/adminv2/sync', false),
            new Node('Student', '/adminv2/sync/student', true)
        );

        /* Navigation
         */
        $allNavigation = new Navigator(
            new Node('Student', '/adminv2/sync/student', true),
            new Node('Parent', '/adminv2/sync/parent', false),
            new Node('Staff', '/adminv2/sync/staff', false)
        );

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/ruleset.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'allNavigation' => $allNavigation->getAllNodes(),

                /* TODO: Replace with data from db
                 */
                'rulesetId' => 'student',
                'ruleset' => $this->getTestRuleset(),
            ]
        ]);
    }

    /**
     * Parent Test Method
     *
     * @param array $params
     */
    private function parent($params) {

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2', false),
            new Node('Sync (UPS)', '/adminv2/sync', false),
            new Node('Parent', '/adminv2/sync/parent', true)
        );

        /* Navigation
         */
        $allNavigation = new Navigator(
            new Node('Student', '/adminv2/sync/student', false),
            new Node('Parent', '/adminv2/sync/parent', true),
            new Node('Staff', '/adminv2/sync/staff', false)
        );

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/ruleset.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'allNavigation' => $allNavigation->getAllNodes(),

                /* TODO: Replace with data from db
                 */
                'rulesetId' => 'parent',
                'ruleset' => $this->getTestRuleset(),
            ]
        ]);
    }

    /**
     * Staff Test Method
     *
     * @param array $params
     */
    private function staff($params) {

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2', false),
            new Node('Sync (UPS)', '/adminv2/sync', false),
            new Node('Staff', '/adminv2/sync/staff', true)
        );

        /* Navigation
         */
        $allNavigation = new Navigator(
            new Node('Student', '/adminv2/sync/student', false),
            new Node('Parent', '/adminv2/sync/parent', false),
            new Node('Staff', '/adminv2/sync/staff', true)
        );

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/ruleset.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'allNavigation' => $allNavigation->getAllNodes(),

                /* TODO: Replace with data from db
                 */
                'rulesetId' => 'staff',
                'ruleset' => $this->getTestRuleset(),
            ]
        ]);
    }

    /**
     * A meta field for various Condition-specific UI
     * so that when you load a Ruleset UI, all Conditions are
     * impossible to accidentally break. You must actively unlock
     * each Condition you want to edit.
     *
     * meta->disabled is the attribute on input, select, textarea elements
     * meta->lock->status is a flag for whether the Condition is locked (1 yes, 0 no)
     * meta->lock->icon is the action button icon- class to start with
     *
     * Should be set to "disabled", "1" and "locked" by default
     */
    private function getMeta() {
        $meta = [
            'disabled' => 'disabled',
            'lock' => [
                'status' => 1,
                'icon' => 'unlocked',
            ]
        ];

        return $meta;
    }

    /**
     * Get an example Ruleset structure
     *
     * @return array
     */
    private function getTestRuleset() {

        /* TODO: Construct Ruleset from database
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

    /**
     * Test Method
     *
     * @param $params
     */
    public function testsubmit($params) {
        new \Alaress_Probe($_POST);
    }
}