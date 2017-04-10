<?php

namespace Custom\Btac\Grades;

use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Controller extends \Schoolbox\Learning\Controller
{
    /**
     * @api GET /learning/grades-year/{userId}/year/{year}
     * @level 165
     * @param User $viewee
     * @param $year
     */
    public function getGradesYear(User $viewee, $year) {

        $assessments = $this->getAssessmentMapperProxy()->createCollection();

        $term = $this->getTermMapper()->createModel();

        $termStart = new \Alaress_Date_Format("{$year}-01-01 00:00:00", \Alaress_Date_Format::DATABASE_FORMAT);
        $termFinish = new \Alaress_Date_Format("{$year}-12-31 23:59:59", \Alaress_Date_Format::DATABASE_FORMAT);

        $term->setStart($termStart);
        $term->setActualFinish($termFinish);
        $term->setDisplayFinish($termFinish);

        $assessments = $assessments->merge($this->getAssessmentMapperProxy()->findAllByStudent($viewee, $term));

        $this->getGrades($viewee, $assessments, $year);
    }

    /**
     * Displays a list of grades/results for the specified user and term for all subjects
     *
     * @api GET /learning/grades/{userId}{/termCode}
     * @level 165
     * @param User $viewee
     * @param Collection|null $assessments
     * @param Term $term
     * @param int|null $year
     * @param Collection $assessments
     */
    public function getGrades(User $viewee, Collection $assessments = null, $year = null, Term $term = null)
    {
        // Storage for each folders markbook
        $markbooks = [];
        $markbooksFolders = [];

        // Keep a collection of all folders student has an assessment in for use in the dropdown

        $assessmentTasks = [];

        if ($assessments == null) {
            $assessments = $this->getAssessmentMapperProxy()->findAllByStudent($viewee, $term);
        }

        // If they are viewing an entire year, default to first semester for results

        if ($term == null) {
            $term = $this->getTermMapper()->findForYear($year)->first();
        }

        $folders = $this->getFolderMapper()->createCollection();

        /** @var IAssessable $assessment */
        foreach ($assessments as $assessment) {
            $folder = $assessment->getContext()->getFolder();

            if ($folder instanceof Folder) {
                if (!isset($foldersFound[$folder->getId()])) {

                    if (!isset($markbooksFolders[$folder->getId()])) {
                        $markbooksFolders[$folder->getId()] = $this->getAssessmentMapperProxy()->createCollection();
                        $folder->loadTeachers();
                        $folders->add($folder);
                    }

                    if ($assessment instanceof DueWork) {
                        if ($assessment->getWorkType()->getName() == "Assessment Task") {
                            if (!isset($assessmentTasks[$folder->getId()])) {
                                $assessmentTasks[$folder->getId()] = [];
                            }
                            $assessmentTasks[$folder->getId()][] = $assessment;
                        }
                    }

                    $markbooksFolders[$folder->getId()]->add($assessment);
                }
            }
        }

        foreach ($markbooksFolders as $folderId => $assessments) {
            $markbooks[$folderId] =
                (new Markbook($assessments, $this->getDefaultGradeGroup(), $this->getUser()))
                    ->getStudentStatistics()[$viewee->getId()];
        }

        // Squish this mess out

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/grades.twig', [
            'student'       => $viewee,
            'term'          => $term,
            'year'          => $year,
            'terms'         => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'       => $folders,
            'assessmentTasks' => $assessmentTasks,
            'markbooks'     => $markbooks,
            'mailHandle'    => $this->getSession()->get('mailhandle'),
            'currentUser'   => $this->getUser(),
        ]));
    }
}
