<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Model as DueWorkFileModel;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkObject;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;

/**
 * Sub-translator for due work specific fields.
 */
class DueWork implements IAssessmentTypeTranslator
{
    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return new DueWorkModel();
    }


    /**
     * @inheritdoc
     */
    public function createDomainObject(?int $id): IAssessable
    {
        return (new DueWorkObject())->setId($id); // TODO consider using ID?
    }


    /**
     * Does this sub-translator recognise this assessable?
     *
     * @param IAssessable $domainObject
     * @return bool
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool
    {
        return $domainObject instanceof DueWorkObject;
    }


    /**
     * Does this sub-translator recognise this assessment?
     *
     * @param IAssessableDataModel $dataModel
     * @return bool
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool
    {
        return $dataModel instanceof DueWorkModel;
    }


    /**
     * @param IAssessable|DueWorkObject         $domainObject
     * @param IAssessableDataModel|DueWorkModel $dataModel
     */
    public function translateToDataModel(IAssessable|DueWorkObject $domainObject, IAssessableDataModel|DueWorkModel $dataModel): void
    {
        // For brevity, these setters are only called if the corresponding getter() returns non-null
        null === $domainObject->getTitle()                  || $dataModel->getAssessmentModel()->setTitle($domainObject->getTitle());
        null === $domainObject->getDescription()            || $dataModel->getAssessmentModel()->setDescription($domainObject->getDescription());
        null === $domainObject->getPublishAt()              || $dataModel->getAssessmentModel()->setPublishDate($domainObject->getPublishAt());
        null === $domainObject->getOpenAt()                 || $dataModel->getAssessmentModel()->setOpenDate($domainObject->getOpenAt());
        null === $domainObject->getDueAt()                  || $dataModel->getAssessmentModel()->setDueDate($domainObject->getDueAt());
        null === $domainObject->getCloseAt()                || $dataModel->getAssessmentModel()->setCloseDate($domainObject->getCloseAt());
        null === $domainObject->getWeight()                 || $dataModel->getAssessmentModel()->setWeight($domainObject->getWeight());
        null === $domainObject->getMarkVisibility()         || $dataModel->setMarkVisibility($domainObject->getMarkVisibility());
        null === $domainObject->getShowSubmittedOnly()      || $dataModel->setSkipUnsubmitted($domainObject->getShowSubmittedOnly());
        null === $domainObject->getCreator()                || $dataModel->setOwner($domainObject->getCreator());
        null === $domainObject->isAcceptMulti()             || $dataModel->setAcceptMultiple($domainObject->isAcceptMulti());
        null === $domainObject->getUseRubric()              || $dataModel->setUseRubric($domainObject->getUseRubric());
        null === $domainObject->getCapabilityMarkType()     || $dataModel->setCapabilityMarkType($domainObject->getCapabilityMarkType());
        null === $domainObject->getCapabilityGradeGroup()   || $dataModel->setCapabilityGradeGroup($domainObject->getCapabilityGradeGroup());
        null === $domainObject->getUseMarkRange()           || $dataModel->setMarkedUsingRange($domainObject->getUseMarkRange());
        null === $domainObject->getSelfReviewability()      || $dataModel->setSelfReviewability($domainObject->getSelfReviewability());
        null === $domainObject->getSimilarityCheckEnabled() || $dataModel->setSimilarityCheckEnabled($domainObject->getSimilarityCheckEnabled());
        null === $domainObject->getEnableWorksheet()        || $dataModel->setEnableWorksheet($domainObject->getEnableWorksheet());
        null === $domainObject->getEnableOffline()          || $dataModel->setEnableOffline($domainObject->getEnableOffline());
        null === $domainObject->getEnableText()             || $dataModel->setEnableText($domainObject->getEnableText());
        null === $domainObject->getEnableFiles()            || $dataModel->setEnableFiles($domainObject->getEnableFiles());
        null === $domainObject->getEnablePolicy()           || $dataModel->setEnablePolicy($domainObject->getEnablePolicy());

        // Kami file _may_ be null though
        $dataModel->setWorksheetTemplate($domainObject->getWorksheetTemplate());
        $dataModel->setKamiWorksheetTemplate($domainObject->getKamiWorksheetTemplate());

        if (null !== $domainObject->isAcceptSingle() && null !== $domainObject->isAcceptMulti()) {
            $dataModel->setAcceptOnline($domainObject->isAcceptSingle() || $domainObject->isAcceptMulti());
        }

        // Convert a mark type into fields
        // TODO move this to the mark scheme translator
        $markScheme = $domainObject->getMarkScheme();
        if ($markScheme instanceof TotalMarkFractional) {
            $dataModel->setMaxMark($markScheme->getTotalMark());
            $dataModel->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_TOTAL));
        } elseif ($markScheme instanceof Percentage) {
            $dataModel->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_PERCENT));
        } elseif ($markScheme instanceof LetterGrade) {
            $dataModel->setGradeGroup($markScheme->getGradeGroup());
            $dataModel->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_LETTER));
        } elseif ($markScheme instanceof None) {
            $dataModel->setMarkType((new DueWorkModel\MarkType())->setId(DueWorkModel\MarkType::TYPE_NONE));
        }

        // Translate files
        if ($domainObject->getAttachedFiles() !== null) {
            $dataModel->setAttachments(new GenericCollection(DueWorkModel\SingleFile\Model::class));

            $seen = [];

            foreach ($domainObject->getAttachedFiles() as $file) {
                /* Skip hashes we've already seen
                 * Won't take into account same file with different filename
                 */
                if (isset($seen[$file->getHash()])) {
                    continue;
                }

                /* Otherwise add to stack and remember we saw this hash before
                 */
                $dataModel->addAttachment(
                    (new DueWorkFileModel())
                        ->setId($file->getId())
                        ->setBox($dataModel)
                        ->setStorage($file->getStorage())
                        ->setBasename($file->getBasename())
                );

                $seen[$file->getHash()] = $file->getHash();
            }
        }
        $dataModel->setExternalId($domainObject->getExternalId());
    }


    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel|DueWorkModel $dataModel
     * @param IAssessable|DueWorkObject         $domainObject
     */
    public function translateToDomainModel(IAssessableDataModel $dataModel, IAssessable $domainObject): void
    {
        // Due work-specific fields
        null === $dataModel->getSkipUnsubmitted()        || $domainObject->setShowSubmittedOnly($dataModel->getSkipUnsubmitted());
        null === $dataModel->getOwner()                  || $domainObject->setCreator($dataModel->getOwner());
        null === $dataModel->getMaxMark()                || $domainObject->setMaxMark($dataModel->getMaxMark());
        null === $dataModel->getGradeGroup()             || $domainObject->setGradeGroup($dataModel->getGradeGroup());
        null === $dataModel->getMarkVisibility()         || $domainObject->setMarkVisibility($dataModel->getMarkVisibility());
        null === $dataModel->getSelfReviewability()      || $domainObject->setSelfReviewability($dataModel->getSelfReviewability());
        null === $dataModel->getSimilarityCheckEnabled() || $domainObject->setSimilarityCheckEnabled($dataModel->getSimilarityCheckEnabled());
        null === $dataModel->getEnableWorksheet()        || $domainObject->setEnableWorksheet($dataModel->getEnableWorksheet());
        null === $dataModel->getEnableOffline()          || $domainObject->setEnableOffline($dataModel->getEnableOffline());
        null === $dataModel->getEnableText()             || $domainObject->setEnableText($dataModel->getEnableText());
        null === $dataModel->getEnableFiles()            || $domainObject->setEnableFiles($dataModel->getEnableFiles());
        null === $dataModel->getEnablePolicy()           || $domainObject->setEnablePolicy($dataModel->getEnablePolicy());

        // Kami file _may_ be null though
        $domainObject->setWorksheetTemplate($dataModel->getWorksheetTemplate());
        $domainObject->setKamiWorksheetTemplate($dataModel->getKamiWorksheetTemplate());

        /* Has Rubric? (actual rubric must be loaded outside of this method)
         * If rubric is off for this Due Work, then clear the rubric-related settings
         */
        if ($dataModel->getUseRubric()) {
            $domainObject->setUseRubric(true);
            $domainObject->setCapabilityMarkType($dataModel->getCapabilityMarkType());
            $domainObject->setCapabilityGradeGroup($dataModel->getCapabilityGradeGroup());

            if ($dataModel->isMarkedUsingRange()) {
                $domainObject->setUseMarkRange(true);
            }
        } else {
            $domainObject->setUseRubric(false);
            $domainObject->setCapabilityMarkType(null);
            $domainObject->setCapabilityGradeGroup(null);
            $domainObject->setUseMarkRange(false);
        }

        // Figure out submission visibility
        if ($dataModel->getAcceptOnline()) {
            if ($dataModel->getAcceptMultiple()) {
                $domainObject->setAcceptsMultiple();
            } else {
                $domainObject->setAcceptsSingle();
            }
        } else {
            $domainObject->setAcceptsNone();
        }

        // Convert attachments
        $domainObject->setAttachedFiles($domainObject->getAttachedFiles()->emptyClone());
        if ($dataModel->getAttachments() !== null) {
            $domainObject->setAttachedFiles(new GenericCollection(DueWorkFile::class));
            foreach ($dataModel->getAttachments() as $attachment) {
                $domainObject->getAttachedFiles()->add(
                    (new DueWorkFile())
                        ->setId($attachment->getId())
                        ->setFilename($attachment->getFilename())
                        ->setStorage($attachment)
                );
            }
        }

        // Convert work type
        $domainObject->setWorkType($dataModel->getAssessmentModel()->getWorkType());

        // Convert mark type
        if (null !== $dataModel->getMarkType()) {
            $domainObject->setMarkType($dataModel->getMarkType());
        }

        $domainObject->setExternalId($dataModel->getExternalId());
    }
}
