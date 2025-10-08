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
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Field\Mapper as LessonPlanFieldMapper;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\File\Mapper as LessonPlanFileMapper;
use Schoolbox\Learning\MarkScheme\None;
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
        $lessonPlanFieldMapper = LessonPlanFieldMapper::getInstance();
        $lessonPlanFileMapper = LessonPlanFileMapper::getInstance();

        /* Remember the Lesson Plan Template that was chosen
         */
        $dataModel->setLessonPlanTemplate($domainObject->getLessonPlanTemplate());

        /* Auditor fields
         */
        $dataModel->setCreatedBy($domainObject->getCreatedBy());
        $dataModel->setUpdatedBy($domainObject->getUpdatedBy());
        $dataModel->setDeletedBy($domainObject->getDeletedBy());

        /* Convert each Lesson Plan Template Field to a Lesson Plan Field
         */
        $dataModel->setFields($lessonPlanFieldMapper->createCollection());

        /* The visibility of this Lesson Plan to its students
         */
        $dataModel->setVisibility($domainObject->getVisibility());

        /* Duration, for the LLM
         */
        $dataModel->setLessonPlanDuration($domainObject->getLessonPlanDuration());

        /* Lesson Plans don't have any marking-related stuff
         * Andy's Filters and the Andy Markbook requires it for all assessment types
         * but we're hardcoding a None markscheme for LPs
         *
         * $dataModel->setMarkScheme(new None());
         */

        /* Take domain fields and shank them into datamodel fields
         *
         * Note that some methods go directly to the Schoolbox Mapper to find resultsets,
         * bypassing augmentWith* method entirely, so objects might not be fully hydrated here.
         * (Lesson Plans on the Course Modify screen, for example; in this case there's no
         * utilisation of a Lesson Plan's fields anyway)
         */
        if (null !== $domainObject->getFields()) {
            foreach ($domainObject->getFields() as $lessonPlanField) {
                $lessonPlanTemplateFieldId = $lessonPlanField->getLessonPlanTemplateField();

                /* Updating a Lesson Plan means we shouldn't use CreateModel() to populate this Field set
                 * otherwise it spawns new ones everywhere
                 */
                $field = $lessonPlanFieldMapper->createModel()
                    ->setId($lessonPlanField->getId())
                    ->setLessonPlan($dataModel)
                    ->setLessonPlanTemplateField($lessonPlanTemplateFieldId)
                    ->setTitle($lessonPlanField->getTitle())
                    ->setDescription($lessonPlanField->getDescription())
                    ->setBody($lessonPlanField->getBody())
                    ->setVisibility($lessonPlanField->getVisibility())
                    ->setSequence($lessonPlanField->getSequence());

                $dataModel->getFields()->add($field);
            }
        }

        // Translate files
        if ($domainObject->getAttachments() !== null) {
            $now = \Alaress_Date_Format::now();

            $dataModel->setAttachments($lessonPlanFileMapper->createCollection());

            foreach ($domainObject->getAttachments() as $file) {
                $dataModel->getAttachments()->add(
                    ($lessonPlanFileMapper->createModel())
                        ->setId($file->getId())
                        ->setLessonPlan($dataModel)
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
     * @param IAssessableDataModel $dataModel
     * @param IAssessable          $domainObject
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel, IAssessable $domainObject): void
    {
        $lessonPlanFieldMapper = LessonPlanFieldMapper::getInstance();
        $lessonPlanFileMapper = LessonPlanFileMapper::getInstance();

        /* Lesson Plan Template associated with this assessment
         */
        $domainObject->setLessonPlanTemplate($dataModel->getLessonPlanTemplate());

        /* Auditor fields
         */
        $domainObject->setCreatedBy($dataModel->getCreatedBy());
        $domainObject->setUpdatedBy($dataModel->getUpdatedBy());
        $domainObject->setDeletedBy($dataModel->getDeletedBy());

        /* Convert each Lesson Plan Template Field to a Lesson Plan Field
         */
        $domainObject->setFields($lessonPlanFieldMapper->createCollection());

        /* The visibility of this Lesson Plan to its students
         */
        $domainObject->setVisibility($dataModel->getVisibility());

        /* Duration, for the LLM
         */
        $domainObject->setLessonPlanDuration($dataModel->getLessonPlanDuration());

        /* Lesson Plans don't have any marking-related stuff
         * Andy's Filters and the Andy Markbook requires it for all assessment types
         * but we're hardcoding a None markscheme for LPs
         */
        $domainObject->setMarkScheme(new None());

        /* Take datamodel fields and shank them into domain fields
         *
         * Note that some methods go directly to the Schoolbox Mapper to find resultsets,
         * bypassing augmentWith* method entirely, so objects might not be fully hydrated here.
         * (Lesson Plans on the Course Modify screen, for example; in this case there's no
         * utilisation of a Lesson Plan's fields anyway)
         */
        if (null !== $dataModel->getFields()) {
            /* findByIdForReview() is used by the project result cache and also the Assessment Module.php
             * to facilitate preloads. Such preloads might be incomplete.
             */
            foreach ($dataModel->getFields() as $lessonPlanField) {
                $lessonPlanTemplateFieldId = $lessonPlanField->getLessonPlanTemplateField();

                $field = $lessonPlanFieldMapper->createModel()
                    ->setId($lessonPlanField->getId())
                    ->setLessonPlan($dataModel)
                    ->setLessonPlanTemplateField($lessonPlanTemplateFieldId)
                    ->setTitle($lessonPlanField->getTitle())
                    ->setDescription($lessonPlanField->getDescription())
                    ->setBody($lessonPlanField->getBody())
                    ->setVisibility($lessonPlanField->getVisibility())
                    ->setSequence($lessonPlanField->getSequence());

                $domainObject->getFields()->add($field);
            }
        }

        // Convert attachments
        $domainObject->setAttachments($lessonPlanFileMapper->createCollection());

        if ($dataModel->getAttachments() !== null) {
            $domainObject->setAttachments($lessonPlanFileMapper->createCollection());

            foreach ($dataModel->getAttachments() as $attachment) {
                $domainObject->getAttachments()->add(
                    ($lessonPlanFileMapper->createModel())
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
