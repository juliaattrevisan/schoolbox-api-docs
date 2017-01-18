<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission;

use Schoolbox\Assessment\Component\SubmissionBox as DueWorkDataObject;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as StudentSubmissionFile;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission as DataObject;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DomainObject;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkSubmissionFile;
use Schoolbox\Mapper;



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
     * @param Collection|DataObject[] $mapperModels
     * @return GenericCollection|DomainObject[]
     */
    protected function transformModels(Collection $mapperModels)
    {
        $dueWorkResponses = new GenericCollection(DomainObject::class);

        foreach ($mapperModels as $studentSubmission) {
            $response = (new DomainObject())
                ->setId($studentSubmission->getId())
                ->setAssessable((new DueWork())->setId($studentSubmission->getSubmissionBox()->getAssessmentModel()->getId()))
                ->setSubmittedBy($studentSubmission->getOwner())
                // TODO add submitted on behalf of as a separate column
                ->setSubmittedOnBehalfOf($studentSubmission->getOwner())
                ->setCreatedAt($studentSubmission->getCreatedAt())
                ->setDeletedAt(new \Alaress_Date_NullDate())
                ->setUpdatedAt($studentSubmission->getUpdatedAt())
                ->setSubmittedAt($studentSubmission->getSubmittedAt());

            foreach ($studentSubmission->getFiles() as $file) {
                // Translate the file subobject
                $responseFile = (new DueWorkSubmissionFile())
                    ->setId($file->getId())
                    ->setStorage($file->getStorage())
                    ->setEditableInline($file->getEditableInline())
                    ->setCrocodocId($file->getUuid())
                    ->setFilename($file->getFilename())
                    ->setPlagscan($file->getPlagscan());

                $response->getAllFiles()->add($responseFile);
            }
            $dueWorkResponses->add($response);
        }

        return $dueWorkResponses;
    }

    /**
     * @param Collection|DomainObject[] $domainObjects
     * @return GenericCollection|DataObject[]
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        $dataModels = new GenericCollection(DataObject::class);

        foreach ($domainObjects as $domainObject) {
            // TODO consider whether the submission box should be looked up in save()
            $dataModel = (new DataObject())
                ->setId($domainObject->getId())
                ->setSubmissionBox(
                    (new DueWorkDataObject())
                        ->setId($this->getInternalValue($domainObject->getAssessable()->getId()))
                )
                ->setOwner($domainObject->getSubmittedOnBehalfOf())
                ->setCreatedAt($domainObject->getCreatedAt())
                ->setSubmittedAt($domainObject->getSubmittedAt());

            // Files
            foreach ($domainObject->getAllFiles() as $file) {

                // The post-save of a file will have to handle saving of plagscan
                // (due to the new Plagscan object not holding a file ID)
                $dataModelFile = (new StudentSubmissionFile())
                    ->setId($file->getId())
                    ->setSubmission($dataModel)
                    ->setStorage($file->getStorage())
                    ->setEditableInline($file->getEditableInline())
                    ->setUuid($file->getCrocodocId())
                    ->setFilename($file->getFilename());

                $dataModel->getFiles()->add($dataModelFile);
            }

            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}
