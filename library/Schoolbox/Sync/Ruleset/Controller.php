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
use \Schoolbox\Sync\Breadcrumb\Facade as Breadcrumbs;
use \Schoolbox\Sync\Breadcrumb\Node as Breadcrumb;

class Controller extends Base {

    /**
     * Render intro
     */
    public function intro() {

        /* Breadcrumbs
         */

        /* Navigation
         */
        $allNavigation = new Breadcrumbs(
            new Breadcrumb('Student', '/adminv2/sync/student', false),
            new Breadcrumb('Parent', '/adminv2/sync/parent', true),
            new Breadcrumb('Staff', '/adminv2/sync/staff', false)
        );

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/intro.twig',
            'data' => [
                'allNavigation' => $allNavigation->getAllBreadcrumbs(),
            ]
        ]);
    }

    /**
     * Test Method
     *
     * @param array $params
     */
    public function student($params) {

        /* A meta field for various Condition-specific UI
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
        $meta = [
            'disabled' => 'disabled',
            'lock' => [
                'status' => 1,
                'icon' => 'unlocked',
            ]
        ];

        /* The actual ruleset construct
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
                                'meta' => $meta
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
                                'meta' => $meta
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
                                'meta' => $meta
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

        /* Breadcrumbs
         */

        /* Navigation
         */
        $allNavigation = new Breadcrumbs(
            new Breadcrumb('Student', '/adminv2/sync/student', true),
            new Breadcrumb('Parent', '/adminv2/sync/parent', false),
            new Breadcrumb('Staff', '/adminv2/sync/staff', false)
        );

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/student.twig',
            'data' => [
                'allNavigation' => $allNavigation->getAllBreadcrumbs(),
                'ruleset' => $ruleset,
            ]
        ]);
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