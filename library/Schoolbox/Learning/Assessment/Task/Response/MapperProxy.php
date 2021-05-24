<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task\Response;

use Schoolbox\Assessment\Course\Task as TaskDataObject;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Course\Task\Completion as DataObject;
use Schoolbox\Assessment\Course\Task\CompletionMapper;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\Task\Response\Model as DomainObject;
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
        $taskResponses = new GenericCollection(DomainObject::class);

        foreach ($mapperModels as $completion) {
            $response = (new DomainObject())
                ->setId($completion->getId())
                ->setAssessable((new Task())->setId($completion->getTask()->getAssessmentModel()->getId()))
                ->setSubmittedBy($completion->getMarkingUser())
                ->setSubmittedOnBehalfOf($completion->getStudent())
                ->setMarkedComplete($completion->getCompletionStatus() != DataObject::STATUS_INCOMPLETE)
                ->setCreatedAt($completion->getCompletionDate())
                ->setUpdatedAt($completion->getCompletionDate())
                ->setDeletedAt($completion->getDeletedAt());

            $taskResponses->add($response);
        }

        return $taskResponses;
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
                ->setStudent($domainObject->getSubmittedOnBehalfOf())
                ->setMarkingUser($domainObject->getSubmittedBy())
                ->setCompletionStatus($domainObject->getCompletionStatus())
                ->setCompletionDate($domainObject->getCreatedAt())
                ->setDeletedAt($domainObject->getDeletedAt());
            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}
