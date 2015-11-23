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
            new Node('Home', '/adminv2/home', false),
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
    public function read($params) {

        /* Produce Twig template data based on Role
         */
        $factory = new Factory($params['rulesetId']);

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/ruleset.twig',
            'data' => $factory->produce()
        ]);
    }

    /**
     * Update each Rule in the Ruleset
     */
    public function update() {

        /* TODO: Database Transaction
         */
        new \Alaress_Probe($_POST);

        /* TODO: Redirect back to Ruleset when finished
         * $this->getResponse()->redirect->to("/adminv2/sync/{$_POST['rulesetId']}");
         */
        echo "Update Rule";
        exit();
    }
}