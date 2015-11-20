<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Condition;

use Alaress\Web\Controller\Base;

class Controller extends Base {

    const CONDITION_GENERAL = 1;
    const CONDITION_USER = 2;

    /**
     * Test Method
     *
     * @param array $params
     */
    public function delete($params) {
        new \Alaress_Probe($params);
    }

    /**
     * Forge a data structure to feed into twig template,
     * so that new Conditions can be injected directly into
     * the ui after being received via ajax
     *
     * @param $params
     */
    public function create($params) {
        switch ($params['conditionType']) {
            case self::CONDITION_GENERAL:
            case self::CONDITION_USER:
                $fragment = $this->getDi()->get('templateEngine')->render('@syncCondition/general.twig', [
                    'currentCondition' => [
                        'condition' => [
                            'id' => rand(0,10000),
                            'type' => $params['conditionType'],
                            'rule_id' => $params['ruleId']
                        ]
                    ]
                ]);

                break;

            default:
                $fragment = '';
                break;
        }

        echo $fragment;
        exit();
    }
}