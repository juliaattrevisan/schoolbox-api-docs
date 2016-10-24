<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task\Completion;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use Schoolbox\Mapper;


/**
 * The new (165+) LMS uses IResponses + IFeedback
 * The old (160-) LMS used traditional Mapper Models
 *
 * This class works with the new objects, and
 * wraps a traditional Mapper to interface with the database.
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
     * @param $externalValues
     * @return array
     */
    protected function getInternalValues(array $externalValues)
    {
        if(!is_array($externalValues)){
            $externalValues = [$externalValues];
        }

        $query = "
            SELECT `id`
            FROM `task`
            WHERE `assessment_id` IN (".implode(',', $externalValues).")
        ";

        $statement = $this->getDatabase()->query($query);
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }


    /**
     * Transforms the Mapper-generated models into the required type.
     *
     * @param Collection|Completion[] $mapperModels
     * @return GenericCollection
     */
    protected function transformModels(Collection $mapperModels)
    {
        $taskCompletions = new GenericCollection(TaskCompletion::class);

        foreach($mapperModels as $completion){
            $taskCompletion = (new TaskCompletion())
                ->setId($completion->getId())
                ->setSubmittedBy($completion->getStudent())
                ->setSubmittedOnBehalfOf($completion->getStudent())
                ->setCreatedAt($completion->getCompletionDate())
                ->setDeletedAt(new \Alaress_Date_NullDate())
                ->setUpdatedAt($completion->getCompletionDate());
            $taskCompletions->add($taskCompletion);
        }

        return $taskCompletions;
    }
}








