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

        /* $mapperModels contains EVERYONE's feedback rows so you have to group them by user, then by response id
         * They might appear totally out of order so you maybe can't assume sequentially grouped rows nicely
         */
        $feedbackStacks = [];

        foreach ($mapperModels as $dataModel) {
            $userId = $dataModel->getResponse()->getLearner()->getId();
            $responseId = $dataModel->getResponse()->getId();

            $feedbackStacks[$userId][$responseId][] = $dataModel;
        }

        /* Foreach of those, compile each feedback $dataModel into the set of Criteria in a Domain representation
         */
        foreach ($feedbackStacks as $userId => $feedbackData) {
            /* These can't really have a primary key: they are an aggregation of many..........
             * Init a GenericCollection to start with
             */
            $domainObject = (new DomainObject())
                ->setCriteria(new GenericCollection(\Schoolbox\Learning\Assessment\DialogAgent\Feedback\Criteria::class));

            /* Collate all the feedback per criterion into one big set
             */
            foreach ($feedbackData as $responseId => $feedbacks) {
                foreach ($feedbacks as $feedback) {
                    /* Populating the feedback object for the first time; you only need to do this once, but you need
                     * something from the $feedback instance to do it (.............wow)
                     */
                    if (!$domainObject->getCriteria()->count()) {
                        $domainObject->setAssessable((new DialogAgentDomainObject())->setId($feedback->getDialogAgent()?->getAssessmentModel()->getId()))
                            ->setResponse($responseTransformer->transformDataModel($feedback->getResponse()))
                            ->setReviewee($feedback->getResponse()->getLearner())
                            /* TODO Who owns this?
                             */
                            ->setReviewer(null)
                            ->setCreatedAt($feedback->getCreatedAt())
                            ->setUpdatedAt($feedback->getUpdatedAt())
                            ->setDeletedAt($feedback->getDeletedAt());
                    }

                    $feedbackObject = (new Criteria())
                        ->setCriteriaId($feedback->getCriteria()->getId())
                        ->setBody($feedback->getBody());


                    $domainObject->getCriteria()->setOffset($feedback->getCriteria()->getId(), $feedbackObject);
                }
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
        $stack = new GenericCollection(DataModel::class);

        $responseTransformer = new Transformer();

        foreach ($domainObjects as $domainObject) {
            $dataModel = new DataModel()
                ->setId($domainObject->getId())
                ->setDialogAgent((new DialogAgentDataModel())->setId($this->getInternalValue($domainObject->getAssessable()->getId())))
                ->setResponse($responseTransformer->transformDomainObject($domainObject->getResponse(), $domainObject->getAssessable()->getId()))
                ->setBody($domainObject->getComment())
                ->setCriteria((new \Schoolbox\Admin\DialogAgent\Scenario\Criteria\Model())->setId($domainObject->getCriteriaId()))
                ->setCreatedAt($domainObject->getCreatedAt())
                ->setUpdatedAt($domainObject->getUpdatedAt())
                ->setDeletedAt($domainObject->getDeletedAt());

            $stack->add($dataModel);
        }

        return $stack;
    }
}
