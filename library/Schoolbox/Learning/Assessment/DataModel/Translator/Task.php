<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Course\Task as TaskModel;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as TaskObject;

/**
 * Sub-translator for task specific fields.
 */
class Task implements IAssessmentTypeTranslator
{
    /**
     * @inheritdoc
     */
    public function translateToDataModel(IAssessable $assessment)
    {
        if ($assessment instanceof TaskObject) {
            // convert to task's model
            // task currently has its own ID, but that will be handled in the mapper proxy
            $model = new TaskModel();
            $model
                ->setTitle($assessment->getTitle())
                ->setOrder(0)
                ->setOwner($assessment->getCreator())
                ->setLink($assessment->getUrl())
                ->setRequiresConfirmation($assessment->isTeacherAssessed())
            ;
            return $model;
        }
        return null;
    }

    /**
     * @inheritdoc
     */
    public function translateToDomainModel(IAssessableDataModel $model)
    {
        if ($model instanceof TaskModel) {
            // Task-specific fields
            $task = (new TaskObject())
                ->setMarkType((new MarkType())->setId(MarkType::TYPE_COMPLETION))
                ->setUrl($model->getLink())
                ->setCreator($model->getOwner())
            ;
            if ($model->getRequiresConfirmation()) {
                $task->teacherAssessed();
            } else {
                $task->selfAssessed();
            }
            return $task;
        }
        return null;
    }
}
