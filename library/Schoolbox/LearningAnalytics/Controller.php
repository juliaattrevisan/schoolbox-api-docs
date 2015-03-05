<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Alaress\Web\Controller\Base;

class Controller extends Base {

    public function index($folderId, $unitId) {

        var_dump($folderId);
        var_dump($unitId);



















        $this->getResponse()->content->set(
            array(
                'template' => '@learningAnalytics/index.twig',
            )
        );
    }


} 