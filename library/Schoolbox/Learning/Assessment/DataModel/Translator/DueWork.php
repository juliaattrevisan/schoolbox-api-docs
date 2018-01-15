<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\DueWork\File as DueWorkFile;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWorkObject;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkWholeNumber;
use Schoolbox\Resource\Storage\Model as StorageModel;

/**
 * Sub-translator for due work specific fields.
 */
class DueWork implements IAssessmentTypeTranslator
{
    private $submissionBoxMapper;

    public function __construct()
    {
        $this->submissionBoxMapper = SubmissionBoxMapper::getInstance(); // TODO inject
    }


    /**
     * @return IAssessableDataModel
     * @throws \Schoolbox\Mapper\Exception
     */
    public function createDataModel(): IAssessableDataModel
    {
        return $this->submissionBoxMapper->createModel(); // TODO consider using ID
    }


    /**
     * @return IAssessable
     */
    public function createDomainObject(): IAssessable
    {
        return new DueWorkObject(); // TODO consider using ID?
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
        return $dataModel instanceof SubmissionBox;
    }


    /**
     * @param IAssessable|DueWorkObject          $domainObject
     * @param IAssessableDataModel|SubmissionBox $dataModel
     * @param int                                $depth
     * @param bool                               $translateProjectContext
     */
    public function translateToDataModel(
        IAssessable $domainObject,
        IAssessableDataModel $dataModel,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void {

        $dataModel
            ->setName($domainObject->getTitle())
            ->setDescription($domainObject->getDescription())
            ->setPublishDate($domainObject->getPublishAt())
            ->setOpenDate($domainObject->getOpenAt())
            ->setDueDate($domainObject->getDueAt())
            ->setLateDate($domainObject->getCloseAt())
            ->setWeight($domainObject->getWeight())
            ->setMarkVisibility($domainObject->getMarkVisibility())
            ->setSkipUnsubmitted($domainObject->getShowSubmittedOnly())
            ->setAcceptOnline($domainObject->isAcceptSingle() || $domainObject->isAcceptMulti())
            ->setAcceptMultiple($domainObject->isAcceptMulti())
            ->setUseRubric($domainObject->getUseRubric())
            ->setMarkedPerRubricGroup($domainObject->getMarkPerCapability())
            ->setOwner($domainObject->getCreator())
            ->setMarkType(new DueWorkModel\MarkType())
            ->setGradeGroup(new DueWorkModel\GradeLevel\Group\Model())
            ->setSelfAssessable($domainObject->getSelfAssessable())
            ->setSelfReflectable($domainObject->getSelfReflectable());

        // Convert a mark type into fields
        // TODO move this to the mark scheme translator
        $markScheme = $domainObject->getMarkScheme();
        if ($markScheme instanceof TotalMarkWholeNumber) {
            $dataModel->setMaxMark($markScheme->getTotalMark());
            $dataModel->getMarkType()->setId(DueWorkModel\MarkType::TYPE_TOTAL);

        } elseif ($markScheme instanceof Percentage) {
            $dataModel->getMarkType()->setId(DueWorkModel\MarkType::TYPE_PERCENT);

        } elseif ($markScheme instanceof LetterGrade) {
            $dataModel->setGradeGroup($markScheme->getGradeGroup());
            $dataModel->getMarkType()->setId(DueWorkModel\MarkType::TYPE_LETTER);

        } elseif ($markScheme instanceof None) {
            $dataModel->getMarkType()->setId(DueWorkModel\MarkType::TYPE_NONE);
        }

        // Translate files
        // TODO consider whether the DueWorkFile model is required - should Storage be used directly?
        foreach ($domainObject->getAttachedFiles() as $file) {
            $dataModel->addAttachment(
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
    }


    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel|SubmissionBox $dataModel
     * @param IAssessable|DueWorkObject          $domainObject
     * @param int                                $depth
     * @param bool                               $translateProjectContext
     */
    public function translateToDomainModel(
        IAssessableDataModel $dataModel,
        IAssessable $domainObject,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void {
        // Due work-specific fields
        $domainObject
            ->setShowSubmittedOnly($dataModel->getSkipUnsubmitted())
            ->setCreator($dataModel->getOwner())
            ->setMaxMark($dataModel->getMaxMark())
            ->setGradeGroup($dataModel->getGradeGroup())
            ->setMarkVisibility($dataModel->getMarkVisibility())
            ->setSelfAssessable($dataModel->getSelfAssessable())
            ->setSelfReflectable($dataModel->getSelfReflectable());

        // Has Rubric?
        if ($dataModel->getUseRubric()) {
            $domainObject->setUseRubric(true);

            if ($dataModel->isMarkedPerRubricGroup()) {
                $domainObject->setMarkPerCapability(true);
            }

            $domainObject->setRubric($dataModel->getRubric());
        } else {
            $domainObject->setUseRubric(false);
            $domainObject->setMarkPerCapability(false);
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
        foreach ($dataModel->getAttachments() as $attachment) {
            $domainObject->getAttachedFiles()->add(
                (new DueWorkFile())
                    ->setFilename($attachment->getFilename())
                    ->setStorage($attachment)
            );
        }

        // Convert work type
        $domainObject->setWorkType($dataModel->getAssessmentModel()->getWorkType());

        // Convert mark type
        $domainObject->setMarkType(
            (new DueWorkModel\MarkType())
                ->setId($dataModel->getMarkType()->getId())
                ->setName($dataModel->getMarkType()->getName())
                ->setSlug($dataModel->getMarkType()->getSlug())
                ->setComment($dataModel->getMarkType()->getComment())
                ->setEnabled($dataModel->getMarkType()->getEnabled())
        );

        // Attach a (possibly empty) Rubric
        $domainObject->setRubric($dataModel->getRubric());
    }
}
