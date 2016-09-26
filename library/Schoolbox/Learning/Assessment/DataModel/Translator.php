<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\Assessment\Component\OnlineAssessment as QuizModel;
use Schoolbox\Assessment\Component\SubmissionBox as DueWorkModel;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Task\Model as Task;
use UnexpectedValueException;

/**
 * Translation class between an Assessment and its data model.
 */
class Translator
{
    /**
     * Translates the Assessment business domain model to a data model.
     *
     * @param IAssessable $assessment
     * @param int         $sequence   The position of this assessment within its container
     * @return IAssessableDataModel
     */
    public function translateToDataModel(IAssessable $assessment, $sequence)
    {
        // Extract assessment base model
        $assessmentModel = new Model();
        $assessmentModel
            ->setId($assessment->getId())
            ->setTitle($assessment->getTitle())
            ->setDescription($assessment->getDescription())
            ->setPublishDate($assessment->getPublishAt())
            ->setOpenDate($assessment->getOpenAt())
            ->setDueDate($assessment->getDueAt())
            ->setCloseDate($assessment->getCloseAt())
            ->setWeight($assessment->getWeight())
            ->setSequence($sequence)
        ;

        // The translation depends on the type of $assessment.
        // Probably doesn't always return a Model either, it will return an old
        // Quiz/DueWork/Task/Project model, with an Assessment attached.
        // TODO figure out which fields on assessable sub-objects are now redundant
        if ($assessment instanceof Project) {
            // convert to project's model
            // TODO implement this
            throw new UnexpectedValueException('$assessment is a project - this method is not implemented for projects yet');

        } elseif ($assessment instanceof DueWork) {
            // convert to due work's model
            $model = new DueWorkModel();
            $model
                ->setAssessmentModel($assessmentModel)
                ->setName($assessment->getTitle())
                ->setDescription($assessment->getDescription())
                ->setPublishDate($assessment->getPublishAt())
                ->setOpenDate($assessment->getOpenAt())
                ->setDueDate($assessment->getDueAt())
                ->setLateDate($assessment->getCloseAt())
                ->setWorkType($assessment->getType())
                ->setWeight($assessment->getWeight())
                ->setAttachments($assessment->getAllFiles())
                ->setSkipUnsubmitted(!$assessment->getShowSubmittedOnly())
                ->setAcceptOnline($assessment->isAcceptSingle() || $assessment->isAcceptMulti())
                ->setAcceptMultiple($assessment->isAcceptMulti())
                ->setUseRubric($assessment->hasRubric())
                ->setOwner($assessment->getCreator())
            ;
            // TODO figure out mark visibility - it's likely obsolete as feedback is manually published
            // TODO figure out rubric result set
            // TODO figure out mark per rubric group
            // TODO set model's grade group, mark type, max mark based on mark scheme
            return $model;

        } elseif ($assessment instanceof Quiz) {
            // convert to quiz's model
            $model = new QuizModel();
            $model
                ->setAssessmentModel($assessmentModel)
                ->setAvailability(QuizModel::AVAILABILITY_ASSESS)
                ->setTitle($assessment->getTitle())
                ->setDescription($assessment->getDescription())
                ->setStart($assessment->getOpenAt())
                ->setFinish($assessment->getDueAt())
                ->setWeight($assessment->getWeight())
                ->setAnonymousResult(false)
                ->setUser($assessment->getCreator())
            ;
            // TODO figure out mark visibility - it's likely obsolete as feedback is manually published
            // TODO add completion message to Quiz
            // TODO add duration to Quiz
            // TODO add max attempts
            // TODO add notify author?
            return $model;

        } elseif ($assessment instanceof Task) {
            // convert to task's model
            // task currently has its own ID, but that will be handled in the mapper proxy
            $model = new TaskModel();
            $model
                ->setAssessmentModel($assessmentModel)
                ->setTitle($assessment->getTitle())
                ->setOrder(0)
                ->setOwner($assessment->getCreator())
                ->setLink($assessment->getLink())
                ->setRequiresConfirmation(!$assessment->isSelfMarkable())
            ;
            return $model;

        }
        throw new UnexpectedValueException('$assessment is not a recognised assessment type');
    }

//    /**
//     * Translates the data model to a domain model.
//     *
//     * @param Model $dataModel
//     * @return IAssessable
//     */
//    public function translateToDomainModel(Model $dataModel)
//    {
//    }
}
