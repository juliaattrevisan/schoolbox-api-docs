<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkObject;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkWholeNumber;
use Schoolbox\Resource\Storage\Model as StorageModel;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;

/**
 * Sub-translator for due work specific fields.
 */
class DueWork implements IAssessmentTypeTranslator
{
    /**
     * @inheritdoc
     */
    public function translateToDataModel(IAssessable $assessment)
    {
        if ($assessment instanceof DueWorkObject) {
            // convert to due work's model
            $model = new DueWorkModel();
            $model
                ->setName($assessment->getTitle())
                ->setDescription($assessment->getDescription())
                ->setPublishDate($assessment->getPublishAt())
                ->setOpenDate($assessment->getOpenAt())
                ->setDueDate($assessment->getDueAt())
                ->setLateDate($assessment->getCloseAt())
                ->setWeight($assessment->getWeight())
                ->setMarkVisibility($assessment->getMarkVisibility())
                ->setSkipUnsubmitted($assessment->getShowSubmittedOnly())
                ->setAcceptOnline($assessment->isAcceptSingle() || $assessment->isAcceptMulti())
                ->setAcceptMultiple($assessment->isAcceptMulti())
                ->setUseRubric($assessment->getUseRubric())
                ->setMarkedPerRubricGroup($assessment->getMarkPerCapability())
                ->setOwner($assessment->getCreator())
                ->setMarkType(new DueWorkModel\MarkType())
                ->setGradeGroup(new DueWorkModel\GradeLevel\Group\Model())
                ->setSelfAssessable($assessment->getSelfAssessable())
                ->setSelfReflectable($assessment->getSelfReflectable())
            ;

            // Convert a mark type into fields
            // TODO move this to the mark scheme translator
            $markScheme = $assessment->getMarkScheme();
            if ($markScheme instanceof TotalMarkWholeNumber) {
                $model->setMaxMark($markScheme->getTotalMark());
                $model->getMarkType()->setId(DueWorkModel\MarkType::TYPE_TOTAL);

            } elseif ($markScheme instanceof Percentage) {
                $model->getMarkType()->setId(DueWorkModel\MarkType::TYPE_PERCENT);

            } elseif ($markScheme instanceof LetterGrade) {
                $model->setGradeGroup($markScheme->getGradeGroup());
                $model->getMarkType()->setId(DueWorkModel\MarkType::TYPE_LETTER);

            } elseif ($markScheme instanceof None) {
                $model->getMarkType()->setId(DueWorkModel\MarkType::TYPE_NONE);

            }

            // Translate files
            // TODO consider whether the DueWorkFile model is required - should Storage be used directly?
            foreach ($assessment->getAttachedFiles() as $file) {
                $model->addAttachment(
                    (new StorageModel())
                        ->setHash($file->getHash())
                        ->setFilename($file->getFilename())
                        ->setExtension($file->getExtension())
                );
            }

            // TODO figure out mark visibility - it's likely obsolete as feedback is manually published
            // TODO figure out rubric result set
            // TODO figure out mark per rubric group
            // TODO set model's grade group, mark type, max mark based on mark scheme
            return $model;
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function translateToDomainModel(IAssessableDataModel $model)
    {
        if ($model instanceof DueWorkModel) {
            // Due work-specific fields
            $assessable = (new DueWorkObject())
                ->setShowSubmittedOnly($model->getSkipUnsubmitted())
                ->setCreator($model->getOwner())
                ->setMaxMark($model->getMaxMark())
                ->setGradeGroup($model->getGradeGroup())
                ->setMarkVisibility($model->getMarkVisibility())

            ;

            // Has Rubric?
            if ($model->getUseRubric()) {
                $assessable->setUseRubric(true);

                if ($model->isMarkedPerRubricGroup()) {
                    $assessable->setMarkPerCapability(true);
                }

                $assessable->setRubric($model->getRubric());
            } else {
                $assessable->setUseRubric(false);
                $assessable->setMarkPerCapability(false);
            }

            // Figure out submission visibility
            if ($model->getAcceptOnline()) {
                if ($model->getAcceptMultiple()) {
                    $assessable->setAcceptsMultiple();
                } else {
                    $assessable->setAcceptsSingle();
                }
            } else {
                $assessable->setAcceptsNone();
            }

            if ($model->getSelfAssessable()) {
                $assessable->setSelfAssessable(true);
            } else {
                $assessable->setSelfAssessable(false);
            }

            if ($model->getSelfReflectable()) {
                $assessable->setSelfReflectable(true);
            } else {
                $assessable->setSelfReflectable(false);
            }

            // Convert attachments
            foreach ($model->getAttachments() as $attachment) {
                $assessable->getAttachedFiles()->add(
                    (new DueWorkFile())
                        ->setFilename($attachment->getFilename())
                        ->setStorage($attachment)
                );
            }

            // Convert work type
            $assessable->setWorkType($model->getAssessmentModel()->getWorkType());

            // Convert mark type
            $assessable->setMarkType(
                (new DueWorkModel\MarkType())
                    ->setId($model->getMarkType()->getId())
                    ->setName($model->getMarkType()->getName())
                    ->setSlug($model->getMarkType()->getSlug())
                    ->setComment($model->getMarkType()->getComment())
                    ->setEnabled($model->getMarkType()->getEnabled())
            );

            // Attach a (possibly empty) Rubric
            $assessable->setRubric($model->getRubric());

            return $assessable;
        }
        return null;
    }
}
