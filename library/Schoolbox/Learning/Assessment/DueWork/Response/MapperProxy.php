<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission as DataObject;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SubmissionMapper as StudentSubmissionMapper;
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
            $transformer ??= new Transformer();
            $dueWorkResponses->add(
                $transformer->transformModel($studentSubmission)
            );
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
            $transformer ??= new Transformer();
            $dataModels->add(
                $transformer->transformDomainObject(
                    $domainObject,
                    $this->getInternalValue($domainObject->getAssessable()->getId())
                )
            );
        }

        return $dataModels;
    }
}
