<?php

namespace Schoolbox\Learning\Assessment\DialogAgent\Response;

use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model as DialogAgentDataModel;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model as DataModel;
use Schoolbox\Learning\Assessment\DialogAgent\Model as DialogAgentDomainObject;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as DomainObject;

/**
 * This shards out the transform* methods because such transformations were used in multiple places:
 * transforming responses themselves, but also the response instance inside a feedback instance.
 *
 * You could potentially solve this with traits, but that requires more thinking.
 */
class Transformer
{
    /**
     * Transforms the db-friendly data model representations into their domain model representations.
     *
     * @param DataModel $dataModel
     * @return DomainObject
     */
    public function transformDataModel(DataModel $dataModel): DomainObject
    {
        $domainObject = new DomainObject()
            ->setId($dataModel->getId())
            ->setAssessable((new DialogAgentDomainObject())->setId($dataModel->getDialogAgent()?->getAssessmentModel()->getId()))
            ->setAuthoritative($dataModel->getAuthoritative())
            ->setStartedAt($dataModel->getStartedAt())
            ->setLockedAt($dataModel->getLockedAt())
            ->setSubmittedAt($dataModel->getSubmittedAt())
            ->setSubmittedBy($dataModel->getSubmittedBy())
            ->setSubmittedOnBehalfOf($dataModel->getLearner())
            ->setSessionId($dataModel->getSessionId())
            ->setAssistanceRequestedAt($dataModel->getAssistanceRequestedAt())
            ->setCreatedAt($dataModel->getCreatedAt())
            ->setCreatedBy($dataModel->getCreatedBy())
            ->setUpdatedAt($dataModel->getUpdatedAt())
            ->setUpdatedBy($dataModel->getUpdatedBy())
            ->setDeletedAt($dataModel->getDeletedAt())
            ->setDeletedBy($dataModel->getDeletedBy());

        return $domainObject;
    }


    /**
     * Transforms the domain model representations into their db-friendly data model representations.
     *
     * @param DomainObject $domainObject
     * @param int $dialogAgentId
     * @return DataModel
     */
    public function transformDomainObject(DomainObject $domainObject, int $dialogAgentId): DataModel
    {
        /* TODO CURRENTLY INCOMPLETE
         *  Transformation from domain objects to data models is yet to be experienced; not sure if we'd ever try
         *  to do it this way, or if we'd just use the datamodel stuff directly. It'd be way cheaper to do that...
         */
        $dataModel = new DataModel()
            ->setId($domainObject->getId())
            ->setDialogAgent((new DialogAgentDataModel())->setId($dialogAgentId))
            ->setLearner($domainObject->getSubmittedOnBehalfOf())
            ->setAuthoritative($domainObject->getAuthoritative())
            ->setStartedAt($domainObject->getStartedAt())
            ->setLockedAt($domainObject->getLockedAt())
            ->setSubmittedAt($domainObject->getSubmittedAt())
            ->setSubmittedBy($domainObject->getSubmittedBy())
            ->setSessionId($domainObject->getSessionId())
            ->setAssistanceRequestedAt($domainObject->getAssistanceRequestedAt())
            ->setCreatedAt($domainObject->getCreatedAt())
            ->setCreatedBy($domainObject->getCreatedBy())
            ->setUpdatedAt($domainObject->getUpdatedAt())
            ->setUpdatedBy($domainObject->getUpdatedBy())
            ->setDeletedAt($domainObject->getDeletedAt())
            ->setDeletedBy($domainObject->getDeletedBy());

        return $dataModel;
    }
}
