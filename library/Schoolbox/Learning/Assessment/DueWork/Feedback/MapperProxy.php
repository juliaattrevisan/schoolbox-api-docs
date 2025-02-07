<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Response\Transformer as ResponseTransformer;
use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Mapper;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkDataObject;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork as DataObject;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DomainObject;

/**
 * The new (165+) LMS uses IResponses + IFeedback
 * The old (160-) LMS used traditional Mapper Models
 *
 * This class works with the new objects, and
 * wraps a traditional Mapper to interface with the database.
 *
 * @method SubmissionReturnMapper getMapper()
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
    private $internalColumnName = 'box_id';

    /**
     * @var string
     */
    private $selectString = 'SELECT id FROM submission_box';

    /**
     * This only works with the Due Work Return mapper
     *
     * @param SubmissionReturnMapper $mapper
     */
    public function __construct(SubmissionReturnMapper $mapper)
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
        $dueWorkFeedbacks = new GenericCollection(DomainObject::class);

        foreach ($mapperModels as $submissionReturn) {
            $responseTransformer ??= new ResponseTransformer();
            $feedback            = (new DomainObject())
                ->setId($submissionReturn->getId())
                ->setAssessable((new DueWork())->setId($submissionReturn->getSubmissionBox()->getAssessmentModel()->getId()))
                ->setReviewee($submissionReturn->getOwner())
                ->setReviewer($submissionReturn->getMarker())
                ->setComment($submissionReturn->getComment())
                ->setNormalisedMark($submissionReturn->getNormalizedMark())
                ->setHumanReadableMark($submissionReturn->getHumanReadableMark())
                ->setHumanReadableInstructorMark($submissionReturn->getHumanReadableInstructorMark())
                ->setResubmissionRequired($submissionReturn->getResubmissionRequired())
                ->setCreatedAt($submissionReturn->getCreatedAt())
                ->setUpdatedAt($submissionReturn->getUpdatedAt())
                ->setPublishedBy($submissionReturn->getPublishedBy())
                ->setPublishedAt($submissionReturn->getPublishedAt())
                ->setDeletedAt($submissionReturn->getDeletedAt());

            if (null !== $submissionReturn->getReviewRoute()) {
                $feedback->setReviewRoute($submissionReturn->getReviewRoute());
            }

            /* Returns don't have to be associated with Submissions
             * This relation is optional
             */
            if ($submissionReturn->getSubmission()) {
                $feedback->setResponse(
                    $responseTransformer->transformModel($submissionReturn->getSubmission())
                );
            }

            // Add all files in return
            foreach ($submissionReturn->getFiles() as $file) {
                $feedback->getAllFiles()->add($file);
            }

            $dueWorkFeedbacks->add($feedback);
        }

        return $dueWorkFeedbacks;
    }

    /**
     * @param Collection|DomainObject[] $domainObjects
     * @return GenericCollection|DataObject[]
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        $dataModels = $this->getMapper()->createCollection();

        foreach ($domainObjects as $domainObject) {
            $responseTransformer ??= new ResponseTransformer();
            $dataModel           = $this->getMapper()->createModel()
                ->setId($domainObject->getId())
                ->setSubmissionBox(
                    (new DueWorkDataObject())
                        ->setId($this->getInternalValue($domainObject->getAssessable()->getId()))
                )
                ->setOwner($domainObject->getReviewee())
                ->setMarker($domainObject->getReviewer())
                ->setComment($domainObject->getComment())
                ->setNormalizedMark($domainObject->getNormalisedMark())
                ->setHumanReadableMark($domainObject->getHumanReadableMark())
                ->setHumanReadableInstructorMark($domainObject->getHumanReadableInstructorMark())
                ->setResubmissionRequired($domainObject->getResubmissionRequired())
                ->setCreatedAt($domainObject->getCreatedAt())
                ->setUpdatedAt($domainObject->getUpdatedAt())
                ->setPublishedBy($domainObject->getPublishedBy())
                ->setPublishedAt($domainObject->getPublishedAt())
                ->setDeletedAt($domainObject->getDeletedAt());

            if (null !== $domainObject->getReviewRoute()) {
                $dataModel->setReviewRoute($domainObject->getReviewRoute());
            }

            // This feedback doesn't have to be tied to a particular response.
            if ($domainObject->getResponse() instanceof IResponse) {
                $dataModel->setSubmission(
                    $responseTransformer->transformDomainObject(
                        $domainObject->getResponse(),
                        $this->getInternalValue($domainObject->getAssessable()->getId())
                    )
                );
            }

            // Add all files
            foreach ($domainObject->getAllFiles() as $file) {
                $dataModel->getFiles()->add($file);
            }

            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}
