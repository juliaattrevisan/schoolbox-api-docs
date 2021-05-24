<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task\Feedback;

use Schoolbox\Assessment\Course\Task as TaskDataObject;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Course\Task\Completion as DataObject;
use Schoolbox\Assessment\Course\Task\CompletionMapper;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponseDomainObject;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as DomainObject;
use Schoolbox\Mapper;

/**
 * The new (165+) LMS uses IResponses + IFeedback
 * The old (160-) LMS used traditional Mapper Models
 *
 * This class works with the new objects, and
 * wraps a traditional Mapper to interface with the database.
 *
 * @method CompletionMapper getMapper()
 */
class MapperProxy extends Mapper\AbstractMapperProxy
{
    /**
     * @var string
     */
    private $externalColumnName = 'assessment_id';

    /**
     * @var string
     */
    private $internalColumnName = 'task_id';

    /**
     * @var string
     */
    private $selectString = 'SELECT id FROM task';

    /**
     * This only works with the Task Completion mapper
     *
     * @param CompletionMapper $mapper
     */
    public function __construct(CompletionMapper $mapper)
    {
        parent::__construct($mapper);
    }


    /**
     * @return Model
     */
    public function createModel()
    {
        /** @var Model $model */
        $model = parent::createModel();
        $model->setResponse(new TaskResponseDomainObject());
        return $model;
    }


    /**
     * @return string
     */
    protected function getModel()
    {
        return DomainObject::class;
    }


    /**
     * @return string
     */
    protected function getExternalColumnName()
    {
        return $this->externalColumnName;
    }

    /**
     * @return string
     */
    protected function getInternalColumnName()
    {
        return $this->internalColumnName;
    }

    /**
     * @return string
     */
    protected function getSelectString()
    {
        return $this->selectString;
    }

    /**
     * Transforms the Mapper-generated models into the required type.
     *
     * @param Collection|DataObject[] $mapperModels
     * @return GenericCollection|DomainObject[]
     */
    protected function transformModels(Collection $mapperModels)
    {
        $taskFeedbacks = new GenericCollection(DomainObject::class);

        foreach ($mapperModels as $completion) {
            // Exclude completions that are self-assessed, these are not considered IFeedbacks
            if (Completion::STATUS_TEACHER_ASSESSED_COMPLETE !== $completion->getCompletionStatus()) {
                continue;
            }

            // The completion serves as both a response and a feedback.
            // Add stub objects to be replaced by caller.

            $taskResponse = (new TaskResponseDomainObject())
                ->setId($completion->getId())
                ->setAssessable((new Task())->setId($completion->getTask()->getAssessmentModel()->getId()))
                ->setSubmittedBy($completion->getMarkingUser())
                ->setSubmittedOnBehalfOf($completion->getStudent())
                ->setCreatedAt($completion->getCompletionDate())
                ->setUpdatedAt($completion->getCompletionDate())
                ->setMarkedComplete($completion->getCompletionStatus() != Completion::STATUS_INCOMPLETE)
                ->setDeletedAt($completion->getDeletedAt());

            $taskFeedback = (new DomainObject())->setResponse($taskResponse);

            $taskFeedbacks->add($taskFeedback);
        }

        return $taskFeedbacks;
    }

    /**
     * @param Collection|DomainObject[] $domainObjects
     * @return GenericCollection|DataObject[]
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        $dataModels = $this->getMapper()->createCollection();

        foreach ($domainObjects as $domainObject) {
            $dataModel = $this->getMapper()->createModel()
                ->setId($domainObject->getId())
                ->setTask(
                    (new TaskDataObject())
                        ->setId($this->getInternalValue($domainObject->getAssessable()->getId()))
                )
                ->setStudent($domainObject->getReviewee())
                ->setMarkingUser($domainObject->getReviewer())
                ->setCompletionDate($domainObject->getCreatedAt())
                ->setCompletionStatus($domainObject->getCompletionStatus())
                ->setDeletedAt($domainObject->getDeletedAt());
            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}
