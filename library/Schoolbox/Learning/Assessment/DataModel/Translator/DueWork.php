<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox;
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
    /** @var DueWorkModel\MarkTypeMapper */
    private $markTypeMapper;

    public function __construct()
    {
        $this->markTypeMapper = DueWorkModel\MarkTypeMapper::getInstance(); // TODO inject
    }


    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return new SubmissionBox();
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
    public function translateToDataModel(IAssessable $domainObject, IAssessableDataModel $dataModel): void
    {
        // For brevity, these setters are only called if the corresponding getter() returns non-null
        null === $domainObject->getTitle()                  || $dataModel->setName($domainObject->getTitle());
        null === $domainObject->getDescription()            || $dataModel->setDescription($domainObject->getDescription());
        null === $domainObject->getPublishAt()              || $dataModel->setPublishDate($domainObject->getPublishAt());
        null === $domainObject->getOpenAt()                 || $dataModel->setOpenDate($domainObject->getOpenAt());
        null === $domainObject->getDueAt()                  || $dataModel->setDueDate($domainObject->getDueAt());
        null === $domainObject->getCloseAt()                || $dataModel->setLateDate($domainObject->getCloseAt());
        null === $domainObject->getWeight()                 || $dataModel->setWeight($domainObject->getWeight());
        null === $domainObject->getMarkVisibility()         || $dataModel->setMarkVisibility($domainObject->getMarkVisibility());
        null === $domainObject->getShowSubmittedOnly()      || $dataModel->setSkipUnsubmitted($domainObject->getShowSubmittedOnly());
        null === $domainObject->isAcceptMulti()             || $dataModel->setAcceptMultiple($domainObject->isAcceptMulti());
        null === $domainObject->getUseRubric()              || $dataModel->setUseRubric($domainObject->getUseRubric());
        null === $domainObject->getMarkPerCapability()      || $dataModel->setMarkedPerCapability($domainObject->getMarkPerCapability());
        null === $domainObject->getUseMarkRange()           || $dataModel->setMarkedUsingRange($domainObject->getUseMarkRange());
        null === $domainObject->getCreator()                || $dataModel->setOwner($domainObject->getCreator());
        null === $domainObject->getSelfReviewability()      || $dataModel->setSelfReviewability($domainObject->getSelfReviewability());
        null === $domainObject->getSimilarityCheckEnabled() || $dataModel->setSimilarityCheckEnabled($domainObject->getSimilarityCheckEnabled());

        if (null !== $domainObject->isAcceptSingle() && null !== $domainObject->isAcceptMulti()) {
            $dataModel->setAcceptOnline($domainObject->isAcceptSingle() || $domainObject->isAcceptMulti());
        }

        // Convert a mark type into fields
        // TODO move this to the mark scheme translator
        $markScheme = $domainObject->getMarkScheme();
        if ($markScheme instanceof TotalMarkFractional) {
            $dataModel->setMaxMark($markScheme->getTotalMark());
            $dataModel->setMarkType($this->markTypeMapper->createModel(DueWorkModel\MarkType::TYPE_TOTAL));
        } elseif ($markScheme instanceof Percentage) {
            $dataModel->setMarkType($this->markTypeMapper->createModel(DueWorkModel\MarkType::TYPE_PERCENT));
        } elseif ($markScheme instanceof LetterGrade) {
            $dataModel->setGradeGroup($markScheme->getGradeGroup());
            $dataModel->setMarkType($this->markTypeMapper->createModel(DueWorkModel\MarkType::TYPE_LETTER));
        } elseif ($markScheme instanceof None) {
            $dataModel->setMarkType($this->markTypeMapper->createModel(DueWorkModel\MarkType::TYPE_NONE));
        }

        // Translate files
        if ($domainObject->getAttachedFiles() !== null) {
            $dataModel->setAttachments(new GenericCollection(SubmissionBox\SingleFile\Model::class));
            foreach ($domainObject->getAttachedFiles() as $file) {
                $dataModel->addAttachment(
                    (new DueWorkFileModel())
                        ->setId($file->getId())
                        ->setBox($dataModel)
                        ->setStorage($file->getStorage())
                        ->setBasename($file->getBasename())
                );
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

        // Has Rubric? (actual rubric must be loaded outside of this method)
        if ($dataModel->getUseRubric()) {
            $domainObject->setUseRubric(true);

            if ($dataModel->isMarkedPerCapability()) {
                $domainObject->setMarkPerCapability(true);
            }

            if ($dataModel->isMarkedUsingRange()) {
                $domainObject->setUseMarkRange(true);
            }
        } else {
            $domainObject->setUseRubric(false);
            $domainObject->setMarkPerCapability(false);
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
