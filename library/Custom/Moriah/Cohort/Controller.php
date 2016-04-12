<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Moriah\Cohort;

use Alaress\Web\Controller\Base;

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
     * @param int $studentId
     */
    public function courseList($studentId)
    {
        $this->getResponse()->content->set([
            'format'   => 'html',
            'template' => '@customMoriahCohort/courseList.twig',
            'data'     => [],
        ]);
    }
}
