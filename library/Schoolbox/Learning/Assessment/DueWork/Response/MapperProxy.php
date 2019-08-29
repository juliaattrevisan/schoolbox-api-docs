<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Schoolbox\Assessment\Component\SubmissionBox as DueWorkDataObject;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission as DataObject;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as DomainObject;
use Schoolbox\Mapper;



/**
 * The new (165+) LMS uses IResponses + IFeedback
 * The old (160-) LMS used traditional Mapper Models
 *
 * This class works with the new objects, and
 * wraps a traditional Mapper to interface with the database.
 *
 * @method StudentSubmissionMapper getMapper()
 * @method Model createModel()
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
        $dueWorkResponses = new GenericCollection(DomainObject::class);

        foreach ($mapperModels as $studentSubmission) {
            $response = (new DomainObject())
                ->setId($studentSubmission->getId())
                ->setAssessable((new DueWork())->setId($studentSubmission->getSubmissionBox()->getAssessmentModel()->getId()))
                ->setSubmittedBy($studentSubmission->getSubmittedBy())
                ->setSubmittedOnBehalfOf($studentSubmission->getOwner())
                ->setCreatedAt($studentSubmission->getCreatedAt())
                ->setDeletedAt(new \Alaress_Date_NullDate())
                ->setUpdatedAt($studentSubmission->getUpdatedAt())
                ->setSubmittedAt($studentSubmission->getSubmittedAt());

            if ($studentSubmission->getFiles() !== null) {
                $response->setAllFiles(new GenericCollection(SingleFile\Model::class));
                foreach ($studentSubmission->getFiles() as $file) {
                    // Translate the file subobject
                    $responseFile = clone $file;
                    $responseFile->setSubmission($response);
                    $response->getAllFiles()->add($responseFile);
                }
            }

            $dueWorkResponses->add($response);
        }

        return $dueWorkResponses;
    }

    /**
     * Sync IDs from data model files to the domain model.
     *
     * @param DomainObject $domainObject
     * @param DataObject   $dataModel
     */
    protected function postSave($domainObject, $dataModel)
    {
        foreach ($dataModel->getFiles() as $dataModelFile) {
            // All we can do is match on hash
            $hash = $dataModelFile->getStorage()->getHash();

            foreach ($domainObject->getAllFiles() as $domainObjectFile) {
                if (!$domainObjectFile->getId() && $domainObjectFile->getStorage()->getHash() == $hash) {
                    // Match found
                    $domainObjectFile->setId($dataModelFile->getId());
                    break;
                }
            }
        }
    }

    /**
     * @param Collection|DomainObject[] $domainObjects
     * @return GenericCollection|DataObject[]
     */
    protected function transformDomainObjects(Collection $domainObjects)
    {
        $dataModels = $this->getMapper()->createCollection();

        foreach ($domainObjects as $domainObject) {
            // TODO consider whether the submission box should be looked up in save()
            $dataModel = $this->getMapper()->createModel()
                ->setId($domainObject->getId())
                ->setSubmissionBox(
                    (new DueWorkDataObject())
                        ->setId($this->getInternalValue($domainObject->getAssessable()->getId()))
                )
                ->setSubmittedBy($domainObject->getSubmittedBy())
                ->setOwner($domainObject->getSubmittedOnBehalfOf())
                ->setCreatedAt($domainObject->getCreatedAt())
                ->setSubmittedAt($domainObject->getSubmittedAt());

            // Files
            if ($domainObject->getAllFiles() !== null) {
                $dataModel->setFiles(new GenericCollection(SingleFile\Model::class));
                foreach ($domainObject->getAllFiles() as $file) {
                    // The post-save of a file will have to handle saving of plagscan
                    // (due to the new Plagscan object not holding a file ID)
                    $dataModelFile = clone $file;
                    $dataModelFile->setSubmission($dataModel);
                    $dataModel->getFiles()->add($dataModelFile);
                }
            }

            $dataModels->add($dataModel);
        }

        return $dataModels;
    }
}
