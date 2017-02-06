<?php

namespace Custom\Btac\Grades;

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

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param TermMapper $termMapper
     * @param GradeGroup $defaultGradeGroup
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        TermMapper $termMapper,
        GradeGroup $defaultGradeGroup
    ) {
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setTermMapper($termMapper);
        $this->setDefaultGradeGroup($defaultGradeGroup);
    }

    /**
     * @param User $viewee
     * @param $year
     */
    public function getGradesYear(User $viewee, $year) {

        $assessments = new Collection();
        $terms = $this->getTermMapper()->findForYear($year);

        /** @var Term $term */
        foreach ($terms as $term) {
            $assessments = $assessments->merge($this->getAssessmentMapperProxy()->findAllByStudent($viewee, $term));
        }

        $this->getGrades($viewee, null, $year, $assessments);
    }

    /**
     * Displays a list of grades/results for the specified user and term for all subjects
     *
     * @api   GET /learning/grades/{userId}{/termCode}
     * @api GET /modules/custom/btac/grades
     * @level 165
     * @param User $viewee
     * @param Term $term
     * @param int|null $year
     * @param Collection $assessments
     */
    public function getGrades(User $viewee, Term $term, $year = null, Collection $assessments)
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

        $this->getResponse()->content->set($this->buildHtmlOutput('@customBtacGrades/grades.twig', [
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

    /**
     * @var TermMapper
     */
    private $termMapper;

    /**
     * @return TermMapper
     */
    protected function getTermMapper()
    {
        return $this->termMapper;
    }

    /**
     * @param TermMapper $termMapper
     * @return static
     */
    protected function setTermMapper(TermMapper $termMapper)
    {
        $this->termMapper = $termMapper;
        return $this;
    }

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }

    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @return static
     */
    protected function setAssessmentMapperProxy(AssessmentMapperProxy $assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        return $this;
    }

    /**
     * @var GradeGroup
     */
    private $defaultGradeGroup;

    /**
     * @return GradeGroup
     */
    protected function getDefaultGradeGroup()
    {
        return $this->defaultGradeGroup;
    }

    /**
     * @param GradeGroup $defaultGradeGroup
     * @return static
     */
    protected function setDefaultGradeGroup(GradeGroup $defaultGradeGroup)
    {
        $this->defaultGradeGroup = $defaultGradeGroup;
        return $this;
    }

}
