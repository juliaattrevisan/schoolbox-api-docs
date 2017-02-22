<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task\Feedback;

use Schoolbox\Assessment\Course\Task as TaskDataObject;
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

        foreach($mapperModels as $completion){

            // Exclude completions that are self-assessed, these are not considered IFeedbacks
            if($completion->getStudent()->getId() == $completion->getMarkingUser()->getId()){
                continue;
            }

            $taskFeedback = (new DomainObject())
                ->setId($completion->getId())
                ->setAssessable((new Task())->setId($completion->getTask()->getAssessmentModel()->getId()))
                // The completion serves as both a response and a feedback.
                // Add a corresponding stub response.
                ->setResponse(
                    (new TaskResponseDomainObject())
                        ->setId($completion->getId())
                )
                ->setReviewee($completion->getStudent())
                ->setReviewer($completion->getMarkingUser())
                ->setCreatedAt($completion->getCompletionDate())
                ->setUpdatedAt($completion->getCompletionDate())
                ->setPublishedAt($completion->getCompletionDate())
                ->setDeletedAt($completion->getDeletedAt());

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
        $dataModels = new GenericCollection(DataObject::class);

        foreach ($domainObjects as $domainObject) {
            $dataModel = (new DataObject())
                ->setId($domainObject->getId())
                ->setTask((new TaskDataObject())->setId($this->getInternalValue($domainObject->getAssessable()->getId())))
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
