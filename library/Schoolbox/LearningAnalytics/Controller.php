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
use Schoolbox\LearningAnalytics\StudentList;

class Controller extends Base {

    public function index(Folder $folder, UnitInstance $unit, Collection $allUnits) {

        $studentListProcessor = new StudentList($folder, $unit);
        $allStudents = $studentListProcessor->getStudentList();

        foreach ($allStudents as $currentStudent) {
            new \Alaress_Probe($currentStudent->getFullName());
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@learningAnalytics/index.twig',
            )
        );
    }


} 