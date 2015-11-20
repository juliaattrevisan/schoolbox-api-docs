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
     * Test method
     *
     * @param $params
     */
    public function create($params) {

        /* Is a factory?
         */
        if ($params['conditionType'] == self::CONDITION_GENERAL) {
        }
        else if ($params['conditionType'] == self::CONDITION_USER) {
        }

        new \Alaress_Probe($params);
    }
}