<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Moriah\Cohort;

use Alaress\Web\Controller\Base;
use Schoolbox\User;

/**
 * Controller for Moriah's cohort page.
 *
 * @package Custom\Moriah\Cohort
 */
class Controller extends Base
{
    /**
     * Action for displaying a list of courses which a student is involved in.
     *
     * @param User $student
     */
    public function courseList(User $student)
    {
        if (!$student->getRole()->isStudent()) {
            // Are they even students?
            throw new \RangeException('This report is only for students');
        }
        $this->getResponse()->content->set([
            'format'   => 'html',
            'template' => '@customMoriahCohort/courseList.twig',
            'data'     => [
                'student' => $student,
            ],
        ]);
    }
}
