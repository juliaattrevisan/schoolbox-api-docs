<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\Feedback;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Lti\Feedback\Model as DomainObject;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\Assessment\Model as LtiAssessmentModel;
use Schoolbox\LTI\Assessment\Score\Mapper;
use Schoolbox\LTI\Assessment\Score\Model as DataObject;
use Schoolbox\Mapper\AbstractMapperProxy;

/**
 * Mapper proxy for LTI assessment feedback.
 *
 * @method Mapper getMapper()
 * @method DomainObject createModel()
 */
class MapperProxy extends AbstractMapperProxy
{

    /**
     * This mapper proxy converts searches on `assessment_id` to
     * `lti_assessment.id`, and converts the searched `assessment_id` values to
     * the matching values of `lti_assessment.id`.
     */
    private const SELECT_STRING   = 'SELECT id FROM lti_assessment';
    private const EXTERNAL_COLUMN = 'assessment_id';
    private const INTERNAL_COLUMN = 'lti_assessment_id';

    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        parent::__construct($mapper);
    }

    /**
     * @inheritdoc
     */
    protected function getModel()
    {
        return Model::class;
    }

    /**
     * @inheritdoc
     */
    protected function getExternalColumnName()
    {
        return self::EXTERNAL_COLUMN;
    }

    /**
     * @inheritdoc
     */
    protected function getInternalColumnName()
    {
        return self::INTERNAL_COLUMN;
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString()
    {
        return self::SELECT_STRING;
    }

    /**
     * Transform mapper models into domain objects.
     *
     * @param Collection|DataObject[] $mapperModels
     * @return GenericCollection|DomainObject[]
     */
    protected function transformModels(Collection $mapperModels)
    {
        /** @var GenericCollection|DomainObject[] $domainObjects */
        $domainObjects = new GenericCollection(DomainObject::class);
        foreach ($mapperModels as $model) {
            // If the model isn't marked as a feedback: skip
            if (!$model->getIsFeedback()) {
                continue;
            }

            $domainObject = $this->createModel()
                ->setId($model->getId())
                ->setAssessable(
                    (new LtiAssessment())
                        ->setId($model->getLtiAssessment()->getAssessmentModel()->getId())
                )
                // Submitted by? Assume it's the student if no marking user is known
                // TODO should submittedBy be nullable?
                ->setReviewer($model->getMarkingUser() ?? $model->getStudent())
                ->setReviewee($model->getStudent())
                ->setComment($model->getComment())
                ->setScoreGiven($model->getScoreGiven())
                ->setScoreMaximum($model->getScoreMaximum())
                ->setPublishedAt($model->getSubmittedAt())
                ->setCreatedAt($model->getCreatedAt())
                ->setUpdatedAt($model->getUpdatedAt())
                ->setDeletedAt($model->getDeletedAt());
            $domainObjects->add($domainObject);
        }

        return $domainObjects;
    }

    /**
     * Transform domain objects into mapper models.
     *
     * @param Collection|DomainObject[] $domainObjects
     * @return GenericCollection|DataObject[]
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        $dataModels = $this->getMapper()->createCollection();
        foreach ($domainObjects as $domainObject) {
            $dataModel = $this->getMapper()->createModel()
                ->setId($domainObject->getId())
                ->setLtiAssessment(
                    (new LtiAssessmentModel())->setId($this->getInternalValue($domainObject->getAssessable()->getId()))
                )
                ->setMarkingUser($domainObject->getReviewer())
                ->setStudent($domainObject->getReviewee())
                ->setComment($domainObject->getComment())
                ->setScoreGiven($domainObject->getScoreGiven())
                ->setScoreMaximum($domainObject->getScoreMaximum())
                ->setSubmittedAt($domainObject->getPublishedAt())
                ->setCreatedAt($domainObject->getCreatedAt())
                ->setUpdatedAt($domainObject->getCreatedAt())
                ->setDeletedAt($domainObject->getDeletedAt());
            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}
