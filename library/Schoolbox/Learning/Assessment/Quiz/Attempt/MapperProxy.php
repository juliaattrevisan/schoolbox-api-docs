<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Attempt;

use Schoolbox\Assessment\Component\OnlineAssessment\Attempt;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
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
    private $internalColumnName = 'assess_id';

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
        if (empty($externalValues)) {
            return [];
        }
        if (!is_array($externalValues)){
            $externalValues = [$externalValues];
        }

        $placeholders = Mapper\PredicateBuilder::getArrayPlaceholders($externalValues);

        $query = "
            SELECT `assess_id`
            FROM `assess`
            WHERE `assessment_id` IN {$placeholders}
        ";

        $statement = $this->getDatabase()->query($query, Mapper\PredicateBuilder::getArrayParameters($externalValues));
        return $statement->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }


    /**
     * Transforms the Mapper-generated models into the required type.
     *
     * @param Collection|Attempt[] $mapperModels
     * @return GenericCollection
     */
    protected function transformModels(Collection $mapperModels)
    {
        $quizResponses = new GenericCollection(QuizAttempt::class);

        foreach ($mapperModels as $studentSubmission){
            $response = (new QuizAttempt())
                ->setId($studentSubmission->getId())
                ->setSubmittedBy($studentSubmission->getUser())
                ->setSubmittedOnBehalfOf($studentSubmission->getUser())
                ->setCreatedAt($studentSubmission->getStart())
                ->setDeletedAt($studentSubmission->getDeleted())
                ->setUpdatedAt($studentSubmission->getFinish());

            // TODO populate individual answers
            $quizResponses->add($response);
        }

        return $quizResponses;
    }
}








