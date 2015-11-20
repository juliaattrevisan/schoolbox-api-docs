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
            'disabled' => '',
            'lock' => [
                'status' => 0,
                'icon' => 'locked',
            ]
        ];

        $data = [
            'currentCondition' => [
                'condition' => [
                    'id' => rand(0, 10000),
                    'type' => $params['conditionType'],
                    'rule_id' => $params['ruleId'],
                    'meta' => $meta,
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