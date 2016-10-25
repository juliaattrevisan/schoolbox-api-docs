<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission;

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
    private $internalQuery = 'SELECT id FROM submission_box';

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
    protected function getInternalQuery()
    {
        return $this->internalQuery;
    }

    /**
     * Transforms the Mapper-generated models into the required type.
     *
     * @param Collection|StudentSubmission[] $mapperModels
     * @return GenericCollection
     */
    protected function transformModels(Collection $mapperModels)
    {
        $dueWorkResponses = new GenericCollection(DueWorkSubmission::class);

        foreach($mapperModels as $studentSubmission){
            $response = (new DueWorkSubmission())
                ->setId($studentSubmission->getId())
                ->setSubmittedBy($studentSubmission->getOwner())
                ->setSubmittedOnBehalfOf($studentSubmission->getOwner())
                ->setCreatedAt($studentSubmission->getCreatedAt())
                ->setDeletedAt(new \Alaress_Date_NullDate())
                ->setUpdatedAt($studentSubmission->getCreatedAt());
            if($hash = $studentSubmission->getHash()){
                $response->getAllFiles()->add(StorageMapper::getInstance()->findByHash($hash));
            }
            $dueWorkResponses->add($response);
        }

        return $dueWorkResponses;
    }
}








