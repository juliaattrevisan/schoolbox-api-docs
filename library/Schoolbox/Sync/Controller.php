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
        $allConditions = array(
            [
                'condition' => [
                    'id' => 1231,
                    'type' => 1,
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
                ],
                'selected' => [
                    'matches' => 1,
                ]
            ]
        );

        /* Twig data
         */
        $this->getResponse()->content->set([
            'template' => '@sync/student.twig',
            'data' => [
                'allConditions' => $allConditions,
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