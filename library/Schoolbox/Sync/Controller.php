<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync;

use Alaress\Web\Controller\Base;

class Controller extends Base {

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

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/student.twig',
            'data' => [
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