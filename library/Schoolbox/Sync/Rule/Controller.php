<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Rule;

use Alaress\Web\Controller\Base;

class Controller extends Base {

    /**
     * Form
     *
     * @param $params
     */
    public function add($params) {
        $this->getResponse()->content->set([
            'template' => '@syncRule/add.twig',
            'data' => [
                'rulesetId' => $params['rulesetId'],

                /* TODO: Empty Rule instance
                 */
                'rule' => []
            ]
        ]);
    }

    /**
     * Perform
     *
     * @param $params
     */
    public function create($params) {

        /* TODO: Database Transaction
         */
        new \Alaress_Probe($params, $_POST);

        /* TODO: Redirect back to Ruleset when finished
         */
        echo "Create Rule";
        exit();
    }

    /**
     * Form
     *
     * @param $params
     */
    public function edit($params) {

        /* TODO: Fetch from db
         */
        $rule = [
            'id' => $params['ruleId'],
            'name' => "Some generic name for ruleId: {$params['ruleId']}",
            'description' => 'Some generic rule description'
        ];

        $this->getResponse()->content->set([
            'template' => '@syncRule/add.twig',
            'data' => [
                'rulesetId' => $params['rulesetId'],
                'rule' => $rule
            ]
        ]);
    }

    /**
     * Perform
     *
     * @param $params
     */
    public function update($params) {

        /* TODO: Database Transaction
         */
        new \Alaress_Probe($params, $_POST);

        /* TODO: Redirect back to Ruleset when finished
         */
        echo "Update Rule";
        exit();
    }

    /**
     * Test Method
     *
     * @param $params
     */
    public function delete($params) {

        /* TODO: Database Transaction
         */
        new \Alaress_Probe($params, $_POST);

        /* TODO: Redirect back to Ruleset when finished
         */
        echo "Delete rule";
        exit();
    }
}