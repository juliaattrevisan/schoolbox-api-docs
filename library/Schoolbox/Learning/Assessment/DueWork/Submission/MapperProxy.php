<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as StudentSubmissionFile;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\File as StudentSubmission;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper as StudentSubmissionMapper;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Mapper;
use \Schoolbox\Resource\Storage\Mapper as StorageMapper;


/**
 * The new (165+) LMS uses IResponses + IFeedback
 * The old (160-) LMS used traditional Mapper Models
 *
 * This class works with the new objects, and
 * wraps a traditional Mapper to interface with the database.
 *
 * @method StudentSubmissionMapper getMapper()
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
     * This only works with the Due Work Submission mapper
     *
     * @param StudentSubmissionMapper $mapper
     */
    public function __construct(StudentSubmissionMapper $mapper)
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
     * @param Collection|StudentSubmission[] $mapperModels
     * @return GenericCollection|DueWorkSubmission[]
     */
    protected function transformModels(Collection $mapperModels)
    {
        $dueWorkResponses = new GenericCollection(DueWorkSubmission::class);

        foreach ($mapperModels as $studentSubmission) {
            $response = (new DueWorkSubmission())
                ->setId($studentSubmission->getId())
                ->setSubmittedBy($studentSubmission->getOwner())
                // TODO add submitted on behalf of as a separate column
                ->setSubmittedOnBehalfOf($studentSubmission->getOwner())
                ->setCreatedAt($studentSubmission->getCreatedAt())
                ->setDeletedAt(new \Alaress_Date_NullDate())
                ->setUpdatedAt($studentSubmission->getUpdatedAt());

            foreach ($studentSubmission->getFiles() as $file) {
                $response->getAllFiles()->add($file->getStorage());
            }
            $dueWorkResponses->add($response);
        }

        return $dueWorkResponses;
    }

    /**
     * @param Collection|DueWorkSubmission[] $domainObjects
     * @return GenericCollection|StudentSubmission[]
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        $dataModels = new GenericCollection(StudentSubmission::class);

        foreach ($domainObjects as $domainObject) {
            $dataModel = (new StudentSubmission())
                ->setOwner($domainObject->getSubmittedBy())
                ->setCreatedAt($domainObject->getCreatedAt());

            // Files
            foreach ($domainObject->getAllFiles() as $file) {
                // TODO this can't be all that needs to be set?
                $dataModel->getFiles()->add(
                    (new StudentSubmissionFile())
                        ->setStorage($file)
                );
            }

            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}








