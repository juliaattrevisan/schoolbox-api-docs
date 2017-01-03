<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Learning\Quiz\Model as QuizModel;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizObject;

/**
 * Sub-translator for quiz specific fields.
 */
class Quiz implements IAssessmentTypeTranslator
{
    /**
     * @inheritdoc
     */
    public function translateToDataModel(IAssessable $assessment)
    {
        if ($assessment instanceof QuizObject) {
            // convert to quiz's model
            $model = new QuizModel();
            $model
                ->setAvailability(QuizModel::AVAILABILITY_ASSESS)
                ->setTitle($assessment->getTitle())
                ->setDescription($assessment->getDescription())
                ->setStart($assessment->getOpenAt())
                ->setFinish($assessment->getDueAt())
                ->setWeight($assessment->getWeight())
                ->setMarkVisibility($assessment->getMarkVisibility())
                ->setAnonymousResult(false)
                ->setUser($assessment->getCreator())
                ->setCompletionMessage($assessment->getCompletionMessage())
                ->setDuration(is_null($assessment->getDuration()) ? 0 : $assessment->getDuration())
                ->setMaxAttempts($assessment->getMaxAttempts())
                ->setMarkVisibility($assessment->getMarkVisibility())
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
        if ($model instanceof QuizModel) {

            // Quiz-specific fields
            $assessable = (new QuizObject())
                ->setMarkType((new MarkType())->setId(MarkType::TYPE_TOTAL))
                ->setMaxAttempts($model->getMaxAttempts())
                ->setMarkVisibility($model->getMarkVisibility())
                ->setDuration($model->getDuration())
                ->setCompletionMessage($model->getCompletionMessage())
                ->setCreator($model->getUser())
            ;
            return $assessable;
        }
        return null;
    }
}
