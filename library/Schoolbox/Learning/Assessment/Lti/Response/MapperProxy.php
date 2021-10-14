<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti\Response;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Lti\Response\Model as DomainObject;
use Schoolbox\Learning\Assessment\Lti\Model as LtiAssessment;
use Schoolbox\LTI\Assessment\Model as LtiAssessmentModel;
use Schoolbox\LTI\Assessment\Score\Mapper;
use Schoolbox\LTI\Assessment\Score\Model as DataObject;
use Schoolbox\Mapper\AbstractMapperProxy;

/**
 * Mapper proxy for LTI assessment responses.
 *
 * @method Mapper getMapper()
 */
class MapperProxy extends AbstractMapperProxy
{
    /**
     * I guess this is the internal
     */
    private const EXTERNAL_COLUMN = 'assessment_id';

    private const INTERNAL_COLUMN = 'lti_assessment_id';

    private const SELECT_STRING = 'SELECT id FROM lti_assessment';

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
            $domainObject = (new DomainObject())
                ->setId($model->getId())
                ->setAssessable(
                    (new LtiAssessment())
                        ->setId($model->getLtiAssessment()->getAssessmentModel()->getId())
                )
                ->setSubmittedBy($model->getMarkingUser())
                ->setSubmittedOnBehalfOf($model->getStudent())
                ->setSubmittedAt($model->getSubmittedAt())
                ->setCreatedAt($model->getCreatedAt())
                ->setUpdatedAt($model->getUpdatedAt())
                ->setDeletedAt($model->getDeletedAt());
            $domainObjects->add($domainObject);
        }

        return $domainObjects;
    }

    /**
     * Transform  domain objects into mapper models.
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
                ->setMarkingUser($domainObject->getSubmittedBy())
                ->setStudent($domainObject->getSubmittedOnBehalfOf())
                ->setSubmittedAt($domainObject->getSubmittedAt())
                ->setCreatedAt($domainObject->getCreatedAt())
                ->setUpdatedAt($domainObject->getCreatedAt())
                ->setDeletedAt($domainObject->getDeletedAt());
            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}
