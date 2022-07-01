<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Schoolbox\Assessment\Component\SubmissionBox as DueWorkDataObject;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission as Model;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DomainObject;

/**
 * Transformer helper for converting due work responses between the domain
 * object and model formats.
 *
 * Required so that the feedback's response is transformed in the same manner
 * that the response itself is.
 */
class Transformer
{
    /**
     * Transform a model to a domain object.
     *
     * @param Model $model
     * @return DomainObject
     */
    public function transformModel(Model $model): DomainObject
    {
        $domainObject = (new DomainObject())
            ->setId($model->getId())
            ->setAssessable((new DueWork())->setId($model->getSubmissionBox()->getAssessmentModel()->getId()))
            ->setSubmittedBy($model->getSubmittedBy())
            ->setSubmittedOnBehalfOf($model->getOwner())
            ->setCreatedAt($model->getCreatedAt())
            ->setDeletedAt($model->getDeletedAt())
            ->setUpdatedAt($model->getUpdatedAt())
            ->setSubmittedAt($model->getSubmittedAt());

        if ($model->getFiles() !== null) {
            $domainObject->setAllFiles(new GenericCollection(SingleFile\Model::class));
            foreach ($model->getFiles() as $file) {
                // Translate the file subobject
                $responseFile = clone $file;
                $responseFile->setSubmission($domainObject);
                $domainObject->getAllFiles()->add($responseFile);
            }
        }

        return $domainObject;
    }

    /**
     * Transform a domain object to a model.
     *
     * @param DomainObject $domainObject
     * @param int          $submissionBoxId The submission box's internal ID:
     *                                      required because this class can't
     *                                      look it up.
     * @return Model
     */
    public function transformDomainObject(DomainObject $domainObject, int $submissionBoxId): Model
    {
        $model = (new Model())
            ->setId($domainObject->getId())
            ->setSubmissionBox(
                (new DueWorkDataObject())
                    ->setId($submissionBoxId)
            )
            ->setSubmittedBy($domainObject->getSubmittedBy())
            ->setOwner($domainObject->getSubmittedOnBehalfOf())
            ->setCreatedAt($domainObject->getCreatedAt())
            ->setSubmittedAt($domainObject->getSubmittedAt())
            ->setDeletedAt($domainObject->getDeletedAt());

        // Files
        if ($domainObject->getAllFiles() !== null) {
            $model->setFiles(new GenericCollection(SingleFile\Model::class));
            foreach ($domainObject->getAllFiles() as $file) {
                // The post-save of a file will have to handle saving of plagscan
                // (due to the new Plagscan object not holding a file ID)
                $dataModelFile = clone $file;
                $dataModelFile->setSubmission($model);
                $model->getFiles()->add($dataModelFile);
            }
        }

        return $model;
    }
}
