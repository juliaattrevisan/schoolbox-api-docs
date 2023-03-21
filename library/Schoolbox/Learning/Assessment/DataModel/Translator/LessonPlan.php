<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Model as LessonPlanModel;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlanObject;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

/**
 * Sub-translator for Lesson Plan specific fields.
 */
class LessonPlan implements IAssessmentTypeTranslator
{
    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return new LessonPlanModel();
    }


    /**
     * @inheritdoc
     */
    public function createDomainObject(?int $id): IAssessable
    {
        return (new LessonPlanObject())->setId($id);
    }


    /**
     * Does this sub-translator recognise this assessment?
     *
     * @param IAssessableDataModel $dataModel
     * @return bool
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool
    {
        return $dataModel instanceof LessonPlanModel;
    }


    /**
     * Does this sub-translator recognise this assessable?
     *
     * @param IAssessable $domainObject
     * @return bool
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool
    {
        return $domainObject instanceof LessonPlanObject;
    }


    /**
     * Copies all attributes from the domainObject to dataModel
     *
     * @param IAssessable|LessonPlanObject         $domainObject
     * @param IAssessableDataModel|LessonPlanModel $dataModel
     * @throws InsufficientHydrationException If domain object is insufficiently hydrated for translation
     */
    public function translateToDataModel(IAssessable $domainObject, IAssessableDataModel $dataModel): void
    {
        /* TODO whatever this is meant to do, like Tasks
         */
//        // Translate files
//        if ($domainObject->getAttachedFiles() !== null) {
//            $now = \Alaress_Date_Format::now();
//
//            $dataModel->setAttachments(new GenericCollection(LessonPlanFileModel::class));
//
//            foreach ($domainObject->getAttachedFiles() as $file) {
//                $dataModel->getAttachments()->add(
//                    (new LessonPlanFileModel())
//                        ->setId($file->getId())
//                        ->setTask($dataModel)
//                        ->setStorage($file->getStorage())
//                        ->setBasename($file->getBasename())
//                        ->setCreatedAt($now)
//                        ->setUpdatedAt($now)
//                );
//            }
//        }
    }


    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel|LessonPlanModel $dataModel
     * @param IAssessable|LessonPlanObject         $domainObject
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel, IAssessable $domainObject): void
    {
        /* TODO whatever this is meant to do, like Tasks
         */
//        // Convert attachments
//        $domainObject->setAttachedFiles($domainObject->getAttachedFiles()->emptyClone());
//
//        if ($dataModel->getAttachments() !== null) {
//            $domainObject->setAttachedFiles(new GenericCollection(LessonPlanFile::class));
//
//            foreach ($dataModel->getAttachments() as $attachment) {
//                $domainObject->getAttachedFiles()->add(
//                    (new LessonPlanFile())
//                        ->setId($attachment->getId())
//                        ->setFilename($attachment->getFilename())
//                        ->setStorage($attachment)
//                        ->setCreatedAt($attachment->getCreatedAt())
//                        ->setUpdatedAt($attachment->getUpdatedAt())
//                        ->setDeletedAt($attachment->getDeletedAt())
//                );
//            }
//        }
    }
}
