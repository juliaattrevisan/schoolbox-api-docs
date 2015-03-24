<?php
/**
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Preview;

use Alaress\Web\Controller\Base;

class Controller extends Base
{

    public function index() {
        echo 'test';

        $this->getResponse()->content->set([
            'template' => '@preview/index.twig',
            'data' => []
        ]);
    }
}
