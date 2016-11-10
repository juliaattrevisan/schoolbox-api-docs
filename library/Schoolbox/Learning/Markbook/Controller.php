<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\ImplementationMapperProxy as CourseImplementationDataSource;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;

class Controller extends Base
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var AssessmentMapperProxy
     */
    protected $assessmentMapperProxy;

    /**
     * @var Participant\DataSource
     */
    protected $participantDataSource;

    /**
     * @var GradeGroup
     */
    protected $defaultGradeGroup;

    /**
     * @var CourseImplementationDataSource
     */
    protected $courseImplementationDataSource;

    /**
     * @var TermMapper
     */
    protected $termMapper;

    /**
     * Constructor
     *
     * @param ErrorMapper                    $errorMapper
     * @param AssessmentMapperProxy          $assessmentMapperProxy
     * @param Participant\DataSource         $participantDataSource
     * @param GradeGroup                     $defaultGradeGroup
     * @param CourseImplementationDataSource $courseImplementationDataSource
     * @param TermMapper                     $termMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        Participant\DataSource $participantDataSource,
        GradeGroup $defaultGradeGroup,
        CourseImplementationDataSource $courseImplementationDataSource,
        TermMapper $termMapper
    ) {
        $this->setErrorMapper($errorMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setParticipantDataSource($participantDataSource);
        $this->setDefaultGradeGroup($defaultGradeGroup);
        $this->setCourseImplementationDataSource($courseImplementationDataSource);
        $this->setTermMapper($termMapper);
    }

    /**
     * Displays a list of marks for students in the specified class
     *
     * @api   GET /learning/markbook/:classId
     * @level 165
     * @param Folder $class
     */
    public function getMarkbook(Folder $class)
    {
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'learningMarkbook.getMarkbookForTerm',
                [
                    'classId'  => $class->getId(),
                    'termCode' => $this->getTermMapper()->findForToday()->getCode(),
                ]
            )
        );
    }

    /**
     * Displays a list of marks for students in the specified class for the given term
     *
     * @api   GET /learning/markbook/:classId/term/:termCode
     * @param Folder $class
     * @param Term   $term
     */
    public function getMarkbookForTerm(Folder $class, Term $term)
    {
        // Replaces /submission/listSubmission/{termSlug}/{folderId}
        //       eg /submission/listSubmission/{termSlug}/{folderId}
        // Replaces /submission/viewSubmission/{dueWorkId}
        //       eg /submission/viewSubmission/56

        // Does this class exist ?
        if (!$class->getId()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        // Generate data: assessments and their Participants
        /** @var Collection|IAssessable[] $assessments */
        $assessments = $this->getAssessmentMapperProxy()->findAllForFolderId($class->getId());

        // Also get anything in a unit spec for that folder
        $assessments->merge(
            $this->getAssessmentMapperProxy()->findAllInCourseImplementationForFolderId($class->getId())
        );

        // Filter by term
        // TODO perform this filtering on a query level
        $assessments = $assessments->filter(
            function (IAssessable $assessment) use ($term) {
                return $term->contains($assessment->getDueAt());
            }
        );

        // Load participants
        foreach ($assessments as $assessment) {
            // Project activities too. ¯\_(ツ)_/¯
            // TODO load participants for activities more intelligently than this
            if ($assessment instanceof Project) {
                foreach ($assessment->getAssessables() as $projectActivity) {
                    $projectActivity->setAllParticipants($this->getParticipantDataSource()->findForAssessment($projectActivity));
                }
            }
            $assessment->setAllParticipants($this->getParticipantDataSource()->findForAssessment($assessment));
        }

        // Build a markbook
        $markbook = new Markbook($assessments, $this->getDefaultGradeGroup());

        // And what is required for a context selector
        $terms = $this->getTermMapper()->findAll(['order' => ['start' => 'desc']]);

        // Also show the courses/units that are on this homepage
        $courses = $this->getCourseImplementationDataSource()->findAllByFolderId($class->getId());

        // And the previous and next term
        $previousTerm = null;
        $nextTerm     = null;
        foreach ($terms as $i => $termInList) {
            if ($termInList->getId() == $term->getId()) {
                if (isset($terms[$i + 1])) {
                    $previousTerm = $terms[$i + 1];
                }
                if (isset($terms[$i - 1])) {
                    $nextTerm = $terms[$i - 1];
                }
                break;
            }
        }

        // And output
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningMarkbook/markbook.twig',
                [
                    'class'        => $class,
                    'markbook'     => $markbook,
                    'terms'        => $terms,
                    'currentTerm'  => $term,
                    'previousTerm' => $previousTerm,
                    'nextTerm'     => $nextTerm,
                    'courses'      => $courses,
                ]
            )->setTitle('Markbook for '.$class->getTitle())
        );
    }

    /* Getters/Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

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
     * @return Participant\DataSource
     */
    protected function getParticipantDataSource()
    {
        return $this->participantDataSource;
    }

    /**
     * @param Participant\DataSource $participantDataSource
     * @return static
     */
    protected function setParticipantDataSource(Participant\DataSource $participantDataSource)
    {
        $this->participantDataSource = $participantDataSource;
        return $this;
    }

    /**
     * @return GradeGroup
     */
    public function getDefaultGradeGroup()
    {
        return $this->defaultGradeGroup;
    }

    /**
     * @param GradeGroup $defaultGradeGroup
     * @return static
     */
    public function setDefaultGradeGroup(GradeGroup $defaultGradeGroup)
    {
        $this->defaultGradeGroup = $defaultGradeGroup;
        return $this;
    }

    /**
     * @return CourseImplementationDataSource
     */
    public function getCourseImplementationDataSource()
    {
        return $this->courseImplementationDataSource;
    }

    /**
     * @param CourseImplementationDataSource $courseImplementationDataSource
     * @return static
     */
    public function setCourseImplementationDataSource(CourseImplementationDataSource $courseImplementationDataSource)
    {
        $this->courseImplementationDataSource = $courseImplementationDataSource;
        return $this;
    }

    /**
     * @return TermMapper
     */
    public function getTermMapper()
    {
        return $this->termMapper;
    }

    /**
     * @param TermMapper $termMapper
     * @return static
     */
    public function setTermMapper(TermMapper $termMapper)
    {
        $this->termMapper = $termMapper;
        return $this;
    }
}
