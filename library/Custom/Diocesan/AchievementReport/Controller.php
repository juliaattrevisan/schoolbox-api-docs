<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport;

use Alaress\Web\Controller\Base;

class Controller extends Base {

    /**
     * Index
     */
    public function index() {
        $this->getResponse()->content->set([
            'template' => '@achievementreport/index.twig',
            'data' => []
        ]);
    }
} 