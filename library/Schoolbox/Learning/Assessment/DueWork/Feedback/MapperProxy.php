<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork as SubmissionReturn;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper as SubmissionReturnMapper;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkResponse;
use Schoolbox\Mapper;
use \Schoolbox\Resource\Storage\Mapper as StorageMapper;

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
     * @param Collection|SubmissionReturn[] $mapperModels
     * @return GenericCollection|DueWorkFeedback[]
     */
    protected function transformModels(Collection $mapperModels)
    {
        $dueWorkFeedbacks = new GenericCollection(DueWorkFeedback::class);

        foreach($mapperModels as $submissionReturn){
            $feedback = (new DueWorkFeedback())
                ->setId($submissionReturn->getId())
                // TODO implement optional association due work feedback association with a response
//                ->setResponse(
//                    (new DueWorkResponse())
//                        ->setId($submissionReturn->getResponse()->getId())
//                )
                ->setReviewee($submissionReturn->getOwner())
                ->setComment($submissionReturn->getComment())
                ->setNormalisedMark($submissionReturn->getNormalizedMark())
                ->setHumanReadableMark($submissionReturn->getHumanReadableMark())
                ->setReviewer($submissionReturn->getMarker())
                ->setCreatedAt($submissionReturn->getCreatedAt())
                ->setUpdatedAt($submissionReturn->getUpdatedAt())
                ->setPublishedAt($submissionReturn->getPublishedAt())
                ->setDeletedAt($submissionReturn->getDeletedAt());
            if($hash = $submissionReturn->getHash()){
                $feedback->getAllFiles()->add(StorageMapper::getInstance()->findByHash($hash));
            }
            $dueWorkFeedbacks->add($feedback);
        }

        return $dueWorkFeedbacks;
    }

    /**
     * @param Collection|DueWorkFeedback[] $domainObjects
     * @return GenericCollection|SubmissionReturn[]
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        $dataModels = new GenericCollection(SubmissionReturn::class);

        foreach ($domainObjects as $domainObject) {
            $dataModel = (new SubmissionReturn())
                ->setOwner($domainObject->getReviewee())
                ->setMarker($domainObject->getReviewer())
                ->setComment($domainObject->getComment())
                ->setNormalizedMark($domainObject->getNormalisedMark())
                ->setHumanReadableMark($domainObject->getHumanReadableMark())
                ->setCreatedAt($domainObject->getCreatedAt())
                ->setUpdatedAt($domainObject->getUpdatedAt())
                ->setPublishedAt($domainObject->getPublishedAt())
                ->setDeletedAt($domainObject->getDeletedAt());

            // Attach file (only first)
            foreach ($domainObject->getAllFiles() as $file) {
                $dataModel->setStorage($file);
                break;
            }

            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}








