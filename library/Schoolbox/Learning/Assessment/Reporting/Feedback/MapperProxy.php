<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Reporting\Feedback;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Model as ReportingDataModel;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper as FeedbackMapper;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Model as DataModel;
use Schoolbox\Learning\Assessment\Reporting\Model as ReportingDomainObject;
use Schoolbox\Learning\Assessment\Reporting\Feedback\Model as DomainObject;
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
    private string $internalColumnName = 'reporting_id';


    /**
     * @var string
     */
    private string $selectString = 'SELECT id FROM reporting';


    /**
     * This only works with the Reporting Feedback mapper
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
     * @param Collection<DataModel> $mapperModels
     * @return GenericCollection<DomainObject>
     */
    protected function transformModels(Collection $mapperModels)
    {
        $stack = new GenericCollection(DomainObject::class);

        foreach ($mapperModels as $dataModel) {
            $domainObject = (new DomainObject())
                ->setId($dataModel->getId())
                ->setAssessable((new ReportingDomainObject())->setId($dataModel->getReporting()?->getAssessmentModel()->getId()))
                ->setReporting($dataModel->getReporting())
                ->setReviewee($dataModel->getLearner())
                ->setReviewer($dataModel->getInstructor())
                ->setNormalisedMark($dataModel->getNormalisedMark())
                ->setHumanReadableMark($dataModel->getHumanReadableMark())
                ->setInstructorMark($dataModel->getHumanReadableInstructorMark())
                ->setComment($dataModel->getComment())
                ->setPublishedAt($dataModel->getPublishedAt())
                ->setPublishedBy($dataModel->getPublishedBy())
                ->setCreatedAt($dataModel->getCreatedAt())
                ->setUpdatedAt($dataModel->getUpdatedAt())
                ->setDeletedAt($dataModel->getDeletedAt());

            $stack->add($domainObject);
        }

        return $stack;
    }


    /**
     * Transforms the domain model representations into their db-friendly data model representations.
     *
     * @param Collection<DomainObject> $domainObjects
     * @return GenericCollection<DataModel>
     *
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        $stack = new GenericCollection(DataModel::class);

        foreach ($domainObjects as $domainObject) {
            $dataModel = new DataModel()
                ->setId($domainObject->getId())
                ->setReporting((new ReportingDataModel())->setId($this->getInternalValue($domainObject->getAssessable()->getId())))
                ->setLearner($domainObject->getReviewee())
                ->setInstructor($domainObject->getReviewer())
                ->setNormalisedMark($domainObject->getNormalisedMark())
                ->setHumanReadableMark($domainObject->getHumanReadableMark())
                ->setHumanReadableInstructorMark($domainObject->getInstructorMark())
                ->setComment($domainObject->getComment())
                ->setPublishedAt($domainObject->getPublishedAt())
                ->setPublishedBy($domainObject->getPublishedBy())
                ->setCreatedAt($domainObject->getCreatedAt())
                ->setUpdatedAt($domainObject->getUpdatedAt())
                ->setDeletedAt($domainObject->getDeletedAt());

            $stack->add($dataModel);
        }

        return $stack;
    }
}
