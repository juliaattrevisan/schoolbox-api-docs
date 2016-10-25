<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Attempt;

use Schoolbox\Assessment\Component\OnlineAssessment\Attempt;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
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
 *
 * @method AttemptMapper getMapper()
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
     * @var string
     */
    private $internalQuery = 'SELECT assess_id FROM assess';

    /**
     * This only works with the Quiz Attempt mapper
     *
     * @param AttemptMapper $mapper
     */
    public function __construct(AttemptMapper $mapper)
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
    protected function getInternalQuery()
    {
        return $this->internalQuery;
    }

    /**
     * Transforms the Mapper-generated models into the required type.
     *
     * @param Collection|Attempt[] $mapperModels
     * @return GenericCollection|QuizAttempt[]
     */
    protected function transformModels(Collection $mapperModels)
    {
        /** @var QuizAttempt[] $quizResponses */
        $quizResponses = new GenericCollection(QuizAttempt::class);

        $ids = [];
        foreach ($mapperModels as $attempt) {
            $ids[] = $attempt->getId();
            $response = (new QuizAttempt())
                ->setId($attempt->getId())
                ->setSubmittedBy($attempt->getUser())
                ->setSubmittedOnBehalfOf($attempt->getUser())
                ->setCreatedAt($attempt->getStart())
                ->setDeletedAt($attempt->getDeleted())
                ->setUpdatedAt($attempt->getFinish())
            ;

            // TODO populate individual answers
            $quizResponses->add($response);
        }

        // Populate the marks available and marks given for each attempt
        $markSets = $this->getMapper()->findSupplementaryInformation($ids);
        foreach ($quizResponses as $response) {
            if (isset($markSets[$response->getId()])) {
                $markSet = $markSets[$response->getId()];
                $response
                    ->setMarksGiven($markSet['mark'])
                    ->setMarksAvailable($markSet['max_mark'])
                    ->setFirstSectionId($markSet['first_section'])
                ;
            }
        }

        return $quizResponses;
    }
}








