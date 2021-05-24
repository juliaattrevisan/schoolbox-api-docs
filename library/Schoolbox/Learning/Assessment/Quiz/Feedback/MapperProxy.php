<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Feedback;

use Schoolbox\Learning\Quiz\Model as QuizDataObject;
use Schoolbox\Learning\Quiz\Attempt\Model as DataObject;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizResponseDomainObject;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as DomainObject;
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
     * Override to return only quizzes which have a reviewed_at time.
     *
     * @param array $columnList
     * @param array $options
     * @return GenericCollection
     */
    public function findByColumnList(array $columnList, $options = [])
    {
        if ($this->isColumnTranslationRequired($columnList)) {
            $columnList = $this->translateColumnList($columnList);
        }

        $pb = $this->getMapper()->getPredicateBuilder();
        $pb->notNull('finish');
        $pb->notNull('reviewed_at');

        // Some of these columns could be empty - handle that predicament
        try {
            $pb->addColumnList($columnList);
        } catch (\UnderflowException $e) {
            return $this->getMapper()->createCollection();
        }

        return $this->findByPredicates($pb, $options);
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
            $ids[] = $attempt->getId();
            $response = (new DomainObject())
                ->setId($attempt->getId())
                ->setAssessable((new Quiz())->setId($attempt->getQuiz()->getAssessmentModel()->getId()))
                // The completion serves as both a response and a feedback.
                // Add a corresponding stub response.
                ->setResponse(
                    (new QuizResponseDomainObject())
                        ->setId($attempt->getId())
                )
                ->setReviewee($attempt->getUser())
                ->setReviewer($attempt->getReviewer())
                ->setPublishedAt($attempt->getReviewed())
                ->setCreatedAt($attempt->getStart())
                ->setDeletedAt($attempt->getDeleted())
                ->setUpdatedAt($attempt->getFinish())
                // TODO figure out if this is the right field
                ->setPublishedAt($attempt->getReviewed())
            ;

            // TODO populate individual answers
            $quizResponses->add($response);
        }

//        // Populate the marks available and marks given for each attempt
//        $markSets = $this->getMapper()->findSupplementaryInformation($ids);
//        foreach ($quizResponses as $response) {
//            if (isset($markSets[$response->getId()])) {
//                $markSet = $markSets[$response->getId()];
//                $response
//                    ->setMarksGiven($markSet['mark'])
//                    ->setMarksAvailable($markSet['max_mark'])
//                    ->setFirstSectionId($markSet['first_section'])
//                ;
//            }
//        }

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
                ->setUser($domainObject->getReviewee())
                ->setStart($domainObject->getCreatedAt())
                ->setFinish($domainObject->getUpdatedAt())
                ->setDeleted($domainObject->getDeletedAt())
                ->setReviewer($domainObject->getReviewer())
                ->setReviewed($domainObject->getPublishedAt());
            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}
