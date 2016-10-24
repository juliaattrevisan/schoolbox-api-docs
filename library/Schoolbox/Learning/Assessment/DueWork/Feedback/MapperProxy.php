<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Feedback;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Mapper;
use \Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * The new (165+) LMS uses DueWorkFeedback which IS A IFeedback
 * The old (160-) LMS used SubmissionReturns.
 *
 * This class accepts and provides DueWorkFeedback objects, and
 * wraps a traditional Mapper to interface with the database.
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
     * @param $externalValues
     * @return array
     */
    protected function getInternalValues(array $externalValues)
    {
        if(!is_array($externalValues)){
            $externalValues = [$externalValues];
        }

        $query = "
            SELECT `id`
            FROM `submission_box`
            WHERE `assessment_id` IN (".implode(',', $externalValues).")
        ";

        $statement = $this->getDatabase()->query($query);
        $statement->execute();
        return $statement->fetchAll(\PDO::FETCH_COLUMN) ?: [];
    }


    /**
     * Transforms the Mapper-generated models into the required type.
     *
     * @param Collection $submissionReturns
     * @return GenericCollection
     */
    protected function transformModels(Collection $submissionReturns)
    {
        $dueWorkFeedbacks = new GenericCollection(DueWorkFeedback::class);

        foreach($submissionReturns as $submissionReturn){
            $feedback = (new DueWorkFeedback())
                ->setId($submissionReturn->getId())
                ->setComment($submissionReturn->getComment())
                ->setNormalisedMark($submissionReturn->getNormalizedMark())
                ->setHumanReadableMark($submissionReturn->getMark())
                ->setReviewer($submissionReturn->getMarker())
                ->setCreatedAt($submissionReturn->getCreatedAt())
                ->setUpdatedAt($submissionReturn->getCreatedAt())
                ->setPublishedAt($submissionReturn->getCreatedAt())
                ->setDeletedAt($submissionReturn->getDeletedAt());
            if($hash = $submissionReturn->getHash()){
                $feedback->getAllFiles()->add(StorageMapper::getInstance()->findByHash($hash));
            }
            $dueWorkFeedbacks->add($feedback);
        }

        return $dueWorkFeedbacks;
    }
}








