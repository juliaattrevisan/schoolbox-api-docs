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
     * Form for creation
     *
     * @param $params
     */
    public function add($params) {
        $this->getResponse()->content->set([
            'template' => '@syncRule/add.twig',
            'data' => [
                'rulesetId' => $params['rulesetId']
            ]
        ]);
    }

    /**
     * Test Method
     *
     * TODO: Redirect back to Ruleset when finished
     *
     * @param $params
     */
    public function create($params) {
        new \Alaress_Probe($params);

        echo "Create Rule";
        exit();
    }

    /**
     * Test Method
     *
     * @param $params
     */
    public function delete($params) {
        new \Alaress_Probe($params);

        echo "Delete rule";
        exit();
    }
}