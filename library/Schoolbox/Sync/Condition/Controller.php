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
     * Delete a Condition asynchronously
     *
     * Return true if deletion was successful
     *
     * TODO: Perform actual deletion from db via mappers etc
     *
     * @return bool
     */
    public function delete() {
        // new \Alaress_Probe($_POST);

        /* Tell us whether the deletion was successful
         * 1 is successfully deleted
         * 0 is not
         */
        $deleted = false;

        if ($deleted) {
            echo true;
            exit();
        }

        exit();
    }

    /**
     * Forge a data structure to feed into twig template,
     * so that new Conditions can be injected directly into
     * the ui after being received via ajax
     *
     * TODO: Populate type and id from database
     *
     * @param $params
     */
    public function create($params) {
        $data = [
            'currentCondition' => [
                'condition' => [
                    'id' => rand(0, 10000),
                    'type' => $params['conditionType'],
                    'rule_id' => $params['ruleId'],

                    /* A meta field which determines whether the "disabled"
                     * attribute is applied to all inputs/selects/textareas
                     * in this Condition
                     */
                    'disabled' => '',
                    'lockStatus' => 0,
                    'lockStatusIcon' => 'locked',
                ]
            ]
        ];

        switch ($params['conditionType']) {
            case self::CONDITION_GENERAL:
                $fragment = $this->getDi()->get('templateEngine')->render('@syncCondition/general.twig', $data);
                break;

            case self::CONDITION_USER:
                $fragment = $this->getDi()->get('templateEngine')->render('@syncCondition/userExists.twig', $data);
                break;

            default:
                $fragment = '';
                break;
        }

        echo $fragment;
        exit();
    }
}