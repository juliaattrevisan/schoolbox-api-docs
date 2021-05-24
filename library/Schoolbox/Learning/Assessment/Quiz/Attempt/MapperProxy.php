<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Attempt;

use Schoolbox\Learning\Quiz\Model as QuizDataObject;
use Schoolbox\Learning\Quiz\Attempt\Model as DataObject;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as DomainObject;
use Schoolbox\Mapper;

/**
 * The new (165+) LMS uses IResponses + IFeedback
 * The old (160-) LMS used traditional Mapper Models
 *
 * This class works with the new objects, and
 * wraps a traditional Mapper to interface with the database.
 *
 * @method QuizAttemptMapper getMapper()
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
    private $selectString = 'SELECT assess_id FROM assess';

    /**
     * This only works with the Quiz Attempt mapper
     *
     * @param QuizAttemptMapper $mapper
     */
    public function __construct(QuizAttemptMapper $mapper)
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
        /** @var DomainObject[] $quizResponses */
        $quizResponses = new GenericCollection(DomainObject::class);

        $ids = [];
        foreach ($mapperModels as $attempt) {
            // Get the last time this record was updated
            $updatedAt = $attempt->getStart();
            /** @var \Alaress_Date_Format[] $interactionPoints */
            $interactionPoints = [$attempt->getFinish(), $attempt->getReviewed()];
            foreach ($interactionPoints as $interaction) {
                if (!is_null($interaction) && !$interaction instanceof \Alaress_Date_NullDate && $interaction->lt($updatedAt)) {
                    $updatedAt = $interaction;
                }
            }

            $ids[] = $attempt->getId();
            $response = (new DomainObject())
                ->setId($attempt->getId())
                ->setAssessable((new Quiz())->setId($attempt->getQuiz()->getAssessmentModel()->getId()))
                ->setSubmittedBy($attempt->getUser())
                ->setSubmittedOnBehalfOf($attempt->getUser())
                ->setCreatedAt($attempt->getStart())
                ->setSubmittedAt($attempt->getFinish())
                ->setDeletedAt($attempt->getDeleted())
                ->setUpdatedAt($updatedAt)
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
                    ->setMarksGiven(floatval($markSet['mark']))
                    ->setMarksAvailable(floatval($markSet['max_mark']))
                    ->setFirstSectionId(intval($markSet['first_section']))
                ;
            }
        }

        return $quizResponses;
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
                ->setQuiz(
                    (new QuizDataObject())
                        ->setId($this->getInternalValue($domainObject->getAssessable()->getId()))
                )
                ->setUser($domainObject->getSubmittedOnBehalfOf())
                ->setStart($domainObject->getCreatedAt())
                ->setFinish($domainObject->getUpdatedAt())
                ->setDeleted($domainObject->getDeletedAt());
                // TODO reviewed field probably needs to be set somehow
//                ->setReviewed($domainObject->getPublishedAt());
            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}
