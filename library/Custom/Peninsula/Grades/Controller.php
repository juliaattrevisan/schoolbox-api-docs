<?php

namespace Custom\Peninsula\Grades;

use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\User;
use \Schoolbox\Learning\Assessment\WorkType;


class Controller extends \Schoolbox\Learning\Grades\Controller
{
    /**
     * @api   GET /learning/grades-year/{userId}/year/{year}
     * @level 165
     * @param User $viewee
     * @param int  $year
     * @throws ServiceNotFound If unable to find mail handler service
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getCustomGradesYear(User $viewee, $year)
    {
        $from        = new \Alaress_Date_Format("{$year}-01-01 00:00:00", \Alaress_Date_Format::DATABASE_FORMAT);
        $to          = new \Alaress_Date_Format("{$year}-12-31 23:59:59", \Alaress_Date_Format::DATABASE_FORMAT);
        $assessments = $this->getAssessmentMapperProxy()->findAllByStudentBetweenDates($viewee, false, $from, $to);

        //If we are looking at the year, no term is specified.
        //Specify term 1 of that year in this case
        $dateString = "{$year}-01-01";
        $term = $this->getTermMapper()->findForDate(
            new \Alaress_Date_Format($dateString, \Alaress_Date_Format::DATABASE_DATE_FORMAT)
        );

        $filteredWorkTypes = WorkType\Mapper::getInstance()->findByColumn('show_on_grades_page', 1);

        $this->getCustomGrades($viewee, $assessments, $year, $term, $filteredWorkTypes);
    }

}
