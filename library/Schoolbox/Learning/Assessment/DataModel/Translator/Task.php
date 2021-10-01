<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\Task\File as TaskFile;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Assessment\Course\Task\File\Model as TaskFileModel;

/**
 * Sub-translator for task specific fields.
 */
class Task implements IAssessmentTypeTranslator
{
    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return new TaskModel();
    }


    /**
     * @inheritdoc
     */
    public function createDomainObject(?int $id): IAssessable
    {
        return (new TaskObject())->setId($id);
    }


    /**
     * Does this sub-translator recognise this assessable?
     *
     * @param IAssessable $domainObject
     * @return bool
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool
    {
        return $domainObject instanceof TaskObject;
    }


    /**
     * Does this sub-translator recognise this assessment?
     *
     * @param IAssessableDataModel $dataModel
     * @return bool
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool
    {
        return $dataModel instanceof TaskModel;
    }


    /**
     * Copies all attributes from the domainObject to dataModel
     *
     * @param IAssessable|TaskObject         $domainObject
     * @param IAssessableDataModel|TaskModel $dataModel
     * @throws InsufficientHydrationException If domain object is insufficiently hydrated for translation
     */
    public function translateToDataModel(IAssessable $domainObject, IAssessableDataModel $dataModel): void
    {

        // convert to task's model
        // task currently has its own ID, but that will be handled in the mapper proxy
        $dataModel
            ->setOwner($domainObject->getCreator())
            ->setLink($domainObject->getUrl())
            ->setRequiresConfirmation(!$domainObject->isSelfAssessable());

        // Translate files
        if ($domainObject->getAttachedFiles() !== null) {
            $now = \Alaress_Date_Format::now();

            $dataModel->setAttachments(new GenericCollection(TaskFileModel::class));

            foreach ($domainObject->getAttachedFiles() as $file) {
                $dataModel->getAttachments()->add(
                    (new TaskFileModel())
                        ->setId($file->getId())
                        ->setTask($dataModel)
                        ->setStorage($file->getStorage())
                        ->setBasename($file->getBasename())
                        ->setCreatedAt($now)
                        ->setUpdatedAt($now)
                );
            }
        }
    }


    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel|TaskModel $dataModel
     * @param IAssessable|TaskObject         $domainObject
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel, IAssessable $domainObject): void
    {

        // Task-specific fields
        $domainObject
            ->setMarkType((new MarkType())->setId(MarkType::TYPE_COMPLETION))
            ->setUrl($dataModel->getLink())
            ->setCreator($dataModel->getOwner())
            ->setIsSelfAssessable(!$dataModel->getRequiresConfirmation());

        // Convert attachments
        $domainObject->setAttachedFiles($domainObject->getAttachedFiles()->emptyClone());

        if ($dataModel->getAttachments() !== null) {
            $domainObject->setAttachedFiles(new GenericCollection(TaskFile::class));

            foreach ($dataModel->getAttachments() as $attachment) {
                $domainObject->getAttachedFiles()->add(
                    (new TaskFile())
                        ->setId($attachment->getId())
                        ->setFilename($attachment->getFilename())
                        ->setStorage($attachment)
                        ->setCreatedAt($attachment->getCreatedAt())
                        ->setUpdatedAt($attachment->getUpdatedAt())
                        ->setDeletedAt($attachment->getDeletedAt())
                );
            }
        }
    }
}
