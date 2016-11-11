<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task\Feedback;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Assessment\Course\Task\CompletionMapper;
use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskResponse;
use Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
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
     * @param Collection|Completion[] $mapperModels
     * @return GenericCollection|TaskFeedback[]
     */
    protected function transformModels(Collection $mapperModels)
    {
        $taskFeedbacks = new GenericCollection(TaskFeedback::class);

        foreach($mapperModels as $completion){
            $taskFeedback = (new TaskFeedback())
                ->setId($completion->getId())
                // The completion serves as both a response and a feedback.
                // Add a corresponding stub response.
                ->setResponse(
                    (new TaskResponse())
                        ->setId($completion->getId())
                )
                ->setReviewee($completion->getStudent())
                ->setReviewer($completion->getMarkingUser())
                ->setCreatedAt($completion->getCompletionDate())
                ->setUpdatedAt($completion->getCompletionDate())
                ->setPublishedAt($completion->getCompletionDate())
                ->setDeletedAt(new \Alaress_Date_NullDate());

            // Set completion of this feedback
            $status = $completion->getCompletionStatus();
            if ($status == Completion::COMPLETION_STATUS_INCOMPLETE) {
                $taskFeedback->markAsNotAttempted();

            } elseif ($status == Completion::COMPLETION_STATUS_REQUIRES_CONFIRMATION) {
                $taskFeedback->markAsSelfAssessed();

            } elseif ($status == Completion::COMPLETION_STATUS_COMPLETE) {
                $taskFeedback->markAsCompleted();

            }

            $taskFeedbacks->add($taskFeedback);
        }

        return $taskFeedbacks;
    }

    /**
     * @param Collection|TaskFeedback[] $domainObjects
     * @return GenericCollection|Completion[]
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        $dataModels = new GenericCollection(Completion::class);

        foreach ($domainObjects as $domainObject) {
            // TODO add task ID - map from assessment ID somehow
            // TODO add completion status once markbook is merged
            $dataModel = (new Completion())
                ->setStudent($domainObject->getReviewee())
                ->setMarkingUser($domainObject->getReviewer())
                ->setCompletionDate($domainObject->getCreatedAt());
            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}








