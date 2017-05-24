<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Mastery;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Assessment\Term;

class Controller extends Base
{
    /**
     * @var Error\Mapper
     */
    private $errorMapper;

    /**
     * @var Assessment\MapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var GradeGroup\Model
     */
    private $defaultGradeGroup;

    /**
     * @var CourseImplementation\Mapper
     */
    private $courseImplementationMapper;

    /**
     * @var Term\Mapper
     */
    private $termMapper;

    /**
     * Constructor
     *
     * @param Error\Mapper                $errorMapper
     * @param Assessment\MapperProxy      $assessmentMapperProxy
     * @param GradeGroup\Model                 $defaultGradeGroup
     * @param CourseImplementation\Mapper $courseImplementationMapper
     * @param Term\Mapper                 $termMapper
     */
    public function __construct(
        Error\Mapper $errorMapper,
        Assessment\MapperProxy $assessmentMapperProxy,
        GradeGroup\Model $defaultGradeGroup,
        CourseImplementation\Mapper $courseImplementationMapper,
        Term\Mapper $termMapper
    ) {
        $this->setErrorMapper($errorMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setDefaultGradeGroup($defaultGradeGroup);
        $this->setCourseImplementationMapper($courseImplementationMapper);
        $this->setTermMapper($termMapper);
    }

    /**
     * @api /learning/mastery/{classId}
     * @param Folder\Model $class
     * @throws ApplicationException
     */
    public function getMastery(Folder\Model $class)
    {
        $this->getMasteryForTerm($class, $this->getTermMapper()->findForToday());
    }

    /**
     * @api /learning/mastery/{classId}/term/{termCode}
     * @param Folder\Model $class
     * @param Term\Model $term
     * @throws ApplicationException
     */
    public function getMasteryForTerm(Folder\Model $class, Term\Model $term)
    {
        // Does this class exist ?
        if (!$class->getId()) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_PAGE_NOT_FOUND);
        }

        // Generate data: assessments and their Participants
        $options = [
            'term'  => $term,
            'order' => ['due_date' => 'ASC'],
        ];
        /** @var Collection|IAssessable[] $assessments */
        $assessments = $this->getAssessmentMapperProxy()->findAllByFolderIdForReview($class->getId(), null, $options)
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
            if ($termInList->getId() === $term->getId()) {
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
     * @return Error\Mapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param Error\Mapper $errorMapper
     * @return static
     */
    protected function setErrorMapper(Error\Mapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return Assessment\MapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }

    /**
     * @param Assessment\MapperProxy $assessmentMapperProxy
     * @return static
     */
    protected function setAssessmentMapperProxy(Assessment\MapperProxy $assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        return $this;
    }

    /**
     * @return GradeGroup\Model
     */
    public function getDefaultGradeGroup()
    {
        return $this->defaultGradeGroup;
    }

    /**
     * @param GradeGroup\Model $defaultGradeGroup
     * @return static
     */
    public function setDefaultGradeGroup(GradeGroup\Model $defaultGradeGroup)
    {
        $this->defaultGradeGroup = $defaultGradeGroup;
        return $this;
    }

    /**
     * @return CourseImplementation\Mapper
     */
    public function getCourseImplementationMapper()
    {
        return $this->courseImplementationMapper;
    }

    /**
     * @param CourseImplementation\Mapper $courseImplementationMapper
     * @return static
     */
    public function setCourseImplementationMapper(CourseImplementation\Mapper $courseImplementationMapper)
    {
        $this->courseImplementationMapper = $courseImplementationMapper;
        return $this;
    }

    /**
     * @return Term\Mapper
     */
    public function getTermMapper()
    {
        return $this->termMapper;
    }

    /**
     * @param Term\Mapper $termMapper
     * @return static
     */
    public function setTermMapper(Term\Mapper $termMapper)
    {
        $this->termMapper = $termMapper;
        return $this;
    }
}
