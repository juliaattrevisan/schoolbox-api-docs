<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Model as ReportingModel;
use Schoolbox\Learning\Assessment\Reporting\Model as ReportingObject;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType; // This is actually a shared concept, so it's not a mistake

/**
 * Sub-translator for Reporting specific fields.
 */
class Reporting implements IAssessmentTypeTranslator
{
    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel
    {
        return new ReportingModel();
    }


    /**
     * @inheritdoc
     */
    public function createDomainObject(?int $id): IAssessable
    {
        return (new ReportingObject())->setId($id);
    }


    /**
     * Does this sub-translator recognise this assessment?
     *
     * @param IAssessableDataModel $dataModel
     * @return bool
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool
    {
        return $dataModel instanceof ReportingModel;
    }


    /**
     * Does this sub-translator recognise this assessable?
     *
     * @param IAssessable $domainObject
     * @return bool
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool
    {
        return $domainObject instanceof ReportingObject;
    }


    /**
     * Copies all attributes from the domainObject to dataModel
     *
     * @param IAssessable|ReportingObject $domainObject A ReportingObject instance
     * @param IAssessableDataModel|ReportingModel $dataModel A ReportingModel instance
     */
    public function translateToDataModel(IAssessable|ReportingObject $domainObject, IAssessableDataModel|ReportingModel $dataModel): void
    {
        $dataModel->setMarkVisibility($domainObject->getMarkVisibility());

        /* Display Mark Types (aka Mark Scheme, for some historical reason)
         */
        $markScheme = $domainObject->getMarkScheme();

        if ($markScheme instanceof TotalMarkFractional) {
            $dataModel->setMaxMark($markScheme->getTotalMark());
            $dataModel->setMarkType((new MarkType())->setId(MarkType::TYPE_TOTAL));
        } elseif ($markScheme instanceof Percentage) {
            $dataModel->setMarkType((new MarkType())->setId(MarkType::TYPE_PERCENT));
        } elseif ($markScheme instanceof LetterGrade) {
            $dataModel->setGradeGroup($markScheme->getGradeGroup());
            $dataModel->setMarkType((new MarkType())->setId(MarkType::TYPE_LETTER));
        } elseif ($markScheme instanceof None) {
            $dataModel->setMarkType((new MarkType())->setId(MarkType::TYPE_NONE));
        }

        /* Instructor Mark Types (aka Mark Scheme, for some historical reason)
         */
        $instructorMarkScheme = $domainObject->getInstructorMarkScheme();

        if ($instructorMarkScheme instanceof TotalMarkFractional) {
            $dataModel->setInstructorMaxMark($instructorMarkScheme->getTotalMark());
            $dataModel->setInstructorMarkType((new MarkType())->setId(MarkType::TYPE_TOTAL));
        } elseif ($instructorMarkScheme instanceof Percentage) {
            $dataModel->setInstructorMarkType((new MarkType())->setId(MarkType::TYPE_PERCENT));
        } elseif ($instructorMarkScheme instanceof LetterGrade) {
            $dataModel->setInstructorGradeGroup($instructorMarkScheme->getGradeGroup());
            $dataModel->setInstructorMarkType((new MarkType())->setId(MarkType::TYPE_LETTER));
        } elseif ($instructorMarkScheme instanceof None) {
            $dataModel->setInstructorMarkType((new MarkType())->setId(MarkType::TYPE_NONE));
        }
    }


    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel|ReportingModel $dataModel
     * @param IAssessable|ReportingObject $domainObject
     */
    public function translateToDomainModel(IAssessableDataModel|ReportingModel $dataModel, IAssessable|ReportingObject $domainObject): void
    {
        $domainObject->setMarkVisibility($dataModel->getMarkVisibility());

        /* Display Mark Types (aka Mark Scheme, for some historical reason)
         */
        if (null !== $dataModel->getMarkType()) {
            $domainObject->setMaxMark($dataModel->getMaxMark());
            $domainObject->setMarkType($dataModel->getMarkType());
            $domainObject->setGradeGroup($dataModel->getGradeGroup());
        }

        /* Instructor Mark Types (aka Mark Scheme, for some historical reason)
         */
        if (null !== $dataModel->getInstructorMarkType()) {
            $domainObject->setInstructorMaxMark($dataModel->getInstructorMaxMark());
            $domainObject->setInstructorMarkType($dataModel->getInstructorMarkType());
            $domainObject->setInstructorGradeGroup($dataModel->getInstructorGradeGroup());
        }
    }
}
