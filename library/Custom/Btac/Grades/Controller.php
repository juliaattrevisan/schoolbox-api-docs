<?php

namespace Custom\Btac\Grades;

use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\Assessment\Term;

class Controller extends \Schoolbox\Learning\Grades\Controller
{

    /**
     * Displays a list of grades/results for the specified user and term for all subjects
     *
     * @api   GET /learning/grades/{userId}{/termCode}
     * @level 165
     * @param User       $viewee
     * @param Term\Model $term
     * @throws ServiceNotFound If unable to find mail handler service
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getGrades(User $viewee, Term\Model $term)
    {
        if (!$term) {
            $term = $this->getTermMapper()->findForToday();
        }
        $assessments  = $this->getAssessmentMapperProxy()->findAllByStudent($viewee, false, $term);

        $year = null;
        $this->getCustomGrades($viewee, $assessments, $year, $term);
    }


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
        $this->getCustomGrades($viewee, $assessments, $year, $term);
    }


    /**
     *
     * @param User                     $viewee
     * @param Collection|IAssessable[] $assessments
     * @param null|int                 $year         Null if looking at a term, instead of a full-year
     * @param Term\Model               $term
     * @throws ServiceNotFound If unable to find mail handler service
     */
    protected function getCustomGrades(User $viewee, Collection $assessments, $year, Term\Model $term)
    {
        // Storage for each folders markbook
        $markbooks        = [];
        $markbooksFolders = [];

        // Keep a collection of all folders student has an assessment in for use in the dropdown
        $assessmentTasks = [];

        $folders = $this->getFolderMapper()->createCollection();

        /** @var IAssessable $assessment */
        foreach ($assessments as $assessment) {
            $folder = $assessment->getContext()->getFolder();

            if ($folder instanceof Folder\Model) {
                if (!isset($foldersFound[$folder->getId()])) {

                    if (!isset($markbooksFolders[$folder->getId()])) {
                        $markbooksFolders[$folder->getId()] = $this->getAssessmentMapperProxy()->createCollection();
                        $folder->loadTeachers();
                        $folders->add($folder);
                    }

                    if ($assessment instanceof DueWork\Model && $assessment->getWorkType()
                        && 'Assessment Task' === $assessment->getWorkType()->getName()
                    ) {
                        if (!isset($assessmentTasks[$folder->getId()])) {
                            $assessmentTasks[$folder->getId()] = [];
                        }
                        $assessmentTasks[$folder->getId()][] = $assessment;
                    }

                    $markbooksFolders[$folder->getId()]->add($assessment);
                }
            }
        }

        foreach ($markbooksFolders as $folderId => $assessmentsArray) {
            $markbooks[$folderId] =
                (new Markbook($assessmentsArray, $this->getDefaultGradeGroup(), $this->getUser()))
                    ->getStudentStatistics()[$viewee->getId()];
        }

        // Squish this mess out

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningGrades/grades.twig', [
            'student'         => $viewee,
            'term'            => $term,
            'year'            => $year,
            'terms'           => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'         => $folders,
            'assessmentTasks' => $assessmentTasks,
            'markbooks'       => $markbooks,
            'mailHandle'      => $this->getSession()->get('mailhandle'),
            'currentUser'     => $this->getUser(),
        ]));
    }

}
