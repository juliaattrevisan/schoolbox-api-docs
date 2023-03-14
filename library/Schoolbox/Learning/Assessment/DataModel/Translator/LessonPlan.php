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
use Schoolbox\Mapper\Exception;
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
     * @param IAssessable          $domainObject  A LessonPlanObject instance
     * @param IAssessableDataModel $dataModel     A LessonPlanModel instance
     * @throws InsufficientHydrationException If domain object is insufficiently hydrated for translation
     * @throws Exception
     */
    public function translateToDataModel(IAssessable $domainObject, IAssessableDataModel $dataModel): void
    {
        $lessonPlanFieldMapper = \Schoolbox\Learning\Assessment\DataModel\LessonPlan\Field\Mapper::getInstance();

        /* Remember the Lesson Plan Template that was chosen
         */
        $dataModel->setLessonPlanTemplate($domainObject->getLessonPlanTemplate());

        /* Convert each Lesson Plan Template Field to a Lesson Plan Field
         */
        $dataModel->setFields($lessonPlanFieldMapper->createCollection());

        /* The visibility of this Lesson Plan to its students
         */
        $dataModel->setVisibility($domainObject->getVisibility());

        /* TODO Domain Object and Data Model don't know about the auditor fields.
        foreach ($domainObject->getLessonPlanTemplate()->getFields() as $lessonPlanTemplateField) {
            $lessonPlanField = $lessonPlanFieldMapper->createModel()
                ->setLessonPlanTemplateField($lessonPlanTemplateField)
                ->setTitle($lessonPlanTemplateField->getTitle())
                ->setBody($lessonPlanTemplateField->getBody())
                ->setVisibility($lessonPlanTemplateField->getVisibility())
                ->setSequence($lessonPlanTemplateField->getSequence());

            $dataModel->getFields()->add($lessonPlanField);
        }

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
        /* Lesson Plan Template associated with this assessment
         */
        $domainObject->setLessonPlanTemplate($dataModel->getLessonPlanTemplate());

        /* Auditor fields
         */
        $domainObject->setCreatedBy($dataModel->getCreatedBy());
        $domainObject->setUpdatedBy($dataModel->getUpdatedBy());
        $domainObject->setDeletedBy($dataModel->getDeletedBy());

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
