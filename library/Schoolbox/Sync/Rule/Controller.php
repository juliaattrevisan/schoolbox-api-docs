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
use \Schoolbox\Sync\Navigator\Navigator;
use \Schoolbox\Sync\Navigator\Node;

class Controller extends Base {

    /**
     * Form
     *
     * @param $params
     */
    public function add($params) {

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2', false),
            new Node('Sync (UPS)', '/adminv2/sync', false),
            new Node(ucwords($params['rulesetId']), "/adminv2/sync/{$params['rulesetId']}", false),
            new Node('Add Rule', '#', true)
        );

        $this->getResponse()->content->set([
            'template' => '@syncRule/add.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
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
        $rulesetId = 'student';

        /* TODO: Fetch from db
         */
        $rule = [
            'id' => $params['ruleId'],
            'name' => "Some generic name for ruleId: {$params['ruleId']}",
            'description' => 'Some generic rule description'
        ];

        /* Breadcrumbs
         */
        $allBreadcrumbs = new Navigator(
            new Node('Home', '/adminv2', false),
            new Node('Sync (UPS)', '/adminv2/sync', false),
            new Node(ucwords($rulesetId), "/adminv2/sync/{$rulesetId}", true)
        );

        $this->getResponse()->content->set([
            'template' => '@syncRule/add.twig',
            'data' => [
                'allBreadcrumbs' => $allBreadcrumbs->getAllNodes(),
                'rulesetId' => $rulesetId,
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