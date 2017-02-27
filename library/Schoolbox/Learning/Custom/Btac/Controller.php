<?php

namespace Schoolbox\Learning\Custom\Btac;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\User;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;

class Controller extends \Schoolbox\Learning\Controller
{
    /**
     * @api GET /learning/grades-year/{userId}/year/{year}
     * @level 165
     * @param User $viewee
     * @param $year
     */
    public function getGradesYear(User $viewee, $year) {

        $assessments = new Collection();

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

        $folders = new Collection();

        /** @var IAssessable $assessment */
        foreach ($assessments as $assessment) {
            $folder = $assessment->getContext()->getFolder();

            if ($folder instanceof Folder) {
                if (!isset($foldersFound[$folder->getId()])) {

                    if (!isset($markbooksFolders[$folder->getId()])) {
                        $markbooksFolders[$folder->getId()] = new Collection();
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

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/Btac/grades.twig', [
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
