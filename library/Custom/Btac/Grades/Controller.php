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

class Controller extends \Schoolbox\Learning\Controller
{

    /**
     * Displays a list of grades/results for the specified user and term for all subjects
     *
     * @api   GET /learning/grades/{userId}{/termCode}
     * @level 165
     * @param User       $viewee
     * @param Term\Model $term
     * @throws ServiceNotFound If unable to find mail handler service
     */
    public function getGrades(User $viewee, Term\Model $term)
    {
        if (!$term) {
            $term = $this->getTermMapper()->findForToday();
        }
        $assessments = $this->getAssessmentMapperProxy()->findAllByStudent($viewee, $term);
        $year        = $term->getStart()->getYear();

        $route = 'learning.getGrades';
        $this->getCustomGrades($viewee, $assessments, $year, $term, $route);
    }


    /**
     * @api   GET /learning/grades-year/{userId}/year/{year}
     * @level 165
     * @param User $viewee
     * @param int  $year
     * @throws ServiceNotFound If unable to find mail handler service
     */
    public function getGradesYear(User $viewee, $year)
    {
        $from        = new \Alaress_Date_Format("{$year}-01-01 00:00:00", \Alaress_Date_Format::DATABASE_FORMAT);
        $to          = new \Alaress_Date_Format("{$year}-12-31 23:59:59", \Alaress_Date_Format::DATABASE_FORMAT);
        $assessments = $this->getAssessmentMapperProxy()->findAllByStudentBetweenDates($viewee, $from, $to);

        // If they are viewing an entire year, default to first semester for results
        // Note that we have one year of assessments, but $term duration may not be a year.
        $term = $this->getTermMapper()->findForYear($year)->first();

        $route = 'learning.getGradesYear';
        $this->getCustomGrades($viewee, $assessments, $year, $term, $route);
    }


    /**
     *
     * @param User                     $viewee
     * @param Collection|IAssessable[] $assessments
     * @param int                      $year
     * @param Term\Model               $term
     * @param string                   $route
     * @throws ServiceNotFound If unable to find mail handler service
     */
    protected function getCustomGrades(User $viewee, Collection $assessments, $year, Term\Model $term, $route)
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

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/grades.twig', [
            'student'         => $viewee,
            'term'            => $term,
            'year'            => $year,
            'terms'           => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'         => $folders,
            'assessmentTasks' => $assessmentTasks,
            'markbooks'       => $markbooks,
            'mailHandle'      => $this->getSession()->get('mailhandle'),
            'currentUser'     => $this->getUser(),
            'route'           => $route,
        ]));
    }

}
