<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent\Feedback;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgentDataModel;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Mapper as FeedbackMapper;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Model as DataModel;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgentDomainObject;
use Schoolbox\Learning\Assessment\DialogAgent\Feedback\Model as DomainObject;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Transformer;
use Schoolbox\Mapper;

/**
 * The new (165+) LMS uses IResponses + IFeedback
 * The old (160-) LMS used traditional Mapper Models
 *
 * This class works with the new objects, and
 * wraps a traditional Mapper to interface with the database.
 *
 * @method FeedbackMapper getMapper()
 * @method DomainObject createModel()
 */
class MapperProxy extends Mapper\AbstractMapperProxy
{
    /**
     * @var string
     */
    private string $externalColumnName = 'assessment_id';


    /**
     * @var string
     */
    private string $internalColumnName = 'dialog_agent_id';


    /**
     * @var string
     */
    private string $selectString = 'SELECT id FROM dialog_agent';


    /**
     * This only works with the Dialog Agent Feedback mapper
     *
     * @param FeedbackMapper $mapper
     */
    public function __construct(FeedbackMapper $mapper)
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
     * Transforms the db-friendly data model representations into their domain model representations.
     *
     * @param Collection|DataModel[] $mapperModels
     * @return GenericCollection|DomainObject[]
     */
    protected function transformModels(Collection $mapperModels)
    {
        $stack = new GenericCollection(DomainObject::class);

        $responseTransformer = new Transformer();

        foreach ($mapperModels as $dataModel) {
            $domainObject = (new DomainObject())
                ->setId($dataModel->getId())
                ->setAssessable((new DialogAgentDomainObject())->setId($dataModel->getDialogAgent()?->getAssessmentModel()->getId()))
                ->setResponse($responseTransformer->transformDataModel($dataModel->getResponse()))
                ->setReviewee($dataModel->getResponse()->getLearner())
                /* Init a stack of domain representation feedback criteria
                 */
                ->setCriteria(new GenericCollection(Criteria\Model::class))
                /* TODO Who owns this?
                 */
                ->setReviewer(null)
                ->setCreatedAt($dataModel->getCreatedAt())
                ->setUpdatedAt($dataModel->getUpdatedAt())
                ->setDeletedAt($dataModel->getDeletedAt());

            /* Accumulate each Feedback Criterion into the set
             */
            /** @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Feedback\Criterion\Model $criterion */
            foreach ($dataModel->getCriteria() as $criterion) {
                $criterionId = $criterion->getScenarioCriterion()->getId();

                $feedbackObject = (new Criteria\Model())
                    ->setId($criterion->getId())
                    ->setCriteriaId($criterion->getScenarioCriterion()->getId())
                    ->setBody($criterion->getBody());

                $domainObject->getCriteria()->setOffset($criterionId, $feedbackObject);
            }

            /* Add this user's collated criteria feedback as one big unit
             */
            $stack->add($domainObject);
        }

        return $stack;
    }


    /**
     * Transforms the domain model representations into their db-friendly data model representations.
     *
     * @param Collection|DomainObject[] $domainObjects
     * @return GenericCollection|DataModel[]
     *
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        /* TODO CURRENTLY INCOMPLETE
         *  I'm not exactly sure when we'd ever try to save this back in this manner via the swamp.
         *  Each cell is fulfilled by AI, not a human, so it's outside the normal workflow that you see.
         *  You're better off using the DataModel Mappers directly, really, for system-generated content
         */
        $stack = new GenericCollection(DataModel::class);

        /* This only saves the domain representation of a feedback, -not- its related feedback criteria fields' values.
         * You'll have to do that separately which is how large-assembly activities do it. For example, in Due Work,
         * you need to set kami files and submission files separately via their *data mappers* which is an interesting
         * plot twist. The AbstractMapperProxy predominantly works on root-level objects, not constituent pieces, unless
         * you override the save() method there...but nothing ever did in any of our learning activities.
         *
         * As I suspected, utilisation of the Data Model mappers is the easiest way to accomplish large-assembly saves.
         */
        $responseTransformer = new Transformer();

        foreach ($domainObjects as $domainObject) {
            $response = $responseTransformer->transformDomainObject($domainObject->getResponse(), $domainObject->getAssessable()->getId());

            $dataModel = new DataModel()
                ->setId($domainObject->getId())
                ->setDialogAgent((new DialogAgentDataModel())->setId($this->getInternalValue($domainObject->getAssessable()->getId())))
                ->setResponse($response)
                ->setLearner($response->getLearner())
                ->setCreatedAt($domainObject->getCreatedAt())
                ->setUpdatedAt($domainObject->getUpdatedAt())
                ->setDeletedAt($domainObject->getDeletedAt());

            $stack->add($dataModel);
        }

        return $stack;
    }
}
