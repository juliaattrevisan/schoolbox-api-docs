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

use Schoolbox\Resource\Category as Folder;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Collection;

class Controller extends Base {

    public function index(Folder $folder, UnitInstance $unit, Collection $allUnits) {

        $this->getResponse()->content->set(
            array(
                'template' => '@learningAnalytics/index.twig',
            )
        );
    }


} 