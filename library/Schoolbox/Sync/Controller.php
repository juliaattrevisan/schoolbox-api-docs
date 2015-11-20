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

                                /* Should be set to "disabled" and "locked" by default
                                 * so that when you load a Ruleset ui, all Conditions are
                                 * impossible to accidentally break. You must actively unlock
                                 * each Condition you want to edit.
                                 *
                                 * disabled is the attribute on input, select, textarea elements
                                 * lockStatus is a flag for whether the Condition is locked (1 yes, 0 no)
                                 * lockStatusIcon is the action button icon- class to start with
                                 */
                                'disabled' => 'disabled',
                                'lockStatus' => 1,
                                'lockStatusIcon' => 'unlocked',
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

                                /* Should be set to "disabled" and "locked" by default
                                 * so that when you load a Ruleset ui, all Conditions are
                                 * impossible to accidentally break. You must actively unlock
                                 * each Condition you want to edit.
                                 *
                                 * disabled is the attribute on input, select, textarea elements
                                 * lockStatus is a flag for whether the Condition is locked (1 yes, 0 no)
                                 * lockStatusIcon is the action button icon- class to start with
                                 */
                                'disabled' => 'disabled',
                                'lockStatus' => 1,
                                'lockStatusIcon' => 'unlocked',
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

                                /* Should be set to "disabled" and "locked" by default
                                 * so that when you load a Ruleset ui, all Conditions are
                                 * impossible to accidentally break. You must actively unlock
                                 * each Condition you want to edit.
                                 *
                                 * disabled is the attribute on input, select, textarea elements
                                 * lockStatus is a flag for whether the Condition is locked (1 yes, 0 no)
                                 * lockStatusIcon is the action button icon- class to start with
                                 */
                                'disabled' => 'disabled',
                                'lockStatus' => 1,
                                'lockStatusIcon' => 'unlocked',
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