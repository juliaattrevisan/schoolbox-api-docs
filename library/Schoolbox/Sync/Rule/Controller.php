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
     * Test Method
     * @param $params
     */
    public function create($params) {
        new \Alaress_Probe($params);

        echo "Create rule";
        exit();
    }

    /**
     * Test Method
     * @param $params
     */
    public function delete($params) {
        new \Alaress_Probe($params);

        echo "Delete rule";
        exit();
    }
}