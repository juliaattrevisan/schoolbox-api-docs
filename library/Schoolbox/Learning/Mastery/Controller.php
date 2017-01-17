<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Mastery;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Markbook\Markbook;
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
     * @var GradeGroup
     */
    protected $defaultGradeGroup;

    /**
     * @var CourseImplementationMapper
     */
    protected $courseImplementationMapper;

    /**
     * @var TermMapper
     */
    protected $termMapper;

    /**
     * Constructor
     *
     * @param ErrorMapper                $errorMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param GradeGroup                 $defaultGradeGroup
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param TermMapper                 $termMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        GradeGroup $defaultGradeGroup,
        CourseImplementationMapper $courseImplementationMapper,
        TermMapper $termMapper
    ) {
        $this->setErrorMapper($errorMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setDefaultGradeGroup($defaultGradeGroup);
        $this->setCourseImplementationMapper($courseImplementationMapper);
        $this->setTermMapper($termMapper);
    }

    /**
     * @param Folder $class
     * @param Term $term
     */
    public function getMastery($class)
    {
        $this->getMasteryForTerm($class, $this->getTermMapper()->findForToday());
    }

    /**
     * @param Folder $class
     * @param Term $term
     */
    public function getMasteryForTerm($class, $term)
    {
        // Does this class exist ?
        if (!$class->getId()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        // Generate data: assessments and their Participants
        /** @var Collection|IAssessable[] $assessments */
        $assessments = $this->getAssessmentMapperProxy()->findAllByFolderIdForReview($class->getId(), null, ['term' => $term])
                                                        ->filter(function (IAssessable $assessable) {
                                                            return !$assessable instanceof Project;
                                                        });

        // Build a markbook
        $markbook = new Markbook($assessments, $this->getDefaultGradeGroup(), $this->getUser());

        // And what is required for a context selector
        $terms = $this->getTermMapper()->findAll(['order' => ['start' => 'desc']]);

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

        $this->getResponse()->content->set([
            'template' => '@learningMastery/mastery.twig',
            'data' => [
                'class'        => $class,
                'markbook'     => $markbook,
                'terms'        => $terms,
                'currentTerm'  => $term,
                'previousTerm' => $previousTerm,
                'nextTerm'     => $nextTerm
            ]
        ]);
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
     * @return CourseImplementationMapper
     */
    public function getCourseImplementationMapper()
    {
        return $this->courseImplementationMapper;
    }

    /**
     * @param CourseImplementationMapper $courseImplementationMapper
     * @return static
     */
    public function setCourseImplementationMapper(CourseImplementationMapper $courseImplementationMapper)
    {
        $this->courseImplementationMapper = $courseImplementationMapper;
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
