<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Model as LessonPlanData;
use Schoolbox\Learning\Assessment\DataModel\Model as DataModel;

class Model
{
    /**
     * Lesson Plan
     */
    private LessonPlanData $lessonPlan;

    /**
     * Linked Assessment
     */
    private DataModel|IAssessable $assessment;

    /**
     * @return int
     */
    public function getLessonPlanId(): int
    {
        return $this->lessonPlan->getId();
    }

    /**
     * @return LessonPlanData
     */
    public function getLessonPlan(): LessonPlanData
    {
        return $this->lessonPlan;
    }

    /**
     * @param LessonPlanData $lessonPlan
     * @return Model
     */
    public function setLessonPlan(LessonPlanData $lessonPlan): self
    {
        $this->lessonPlan = $lessonPlan;
        return $this;
    }

    /**
     * @return int
     */
    public function getAssessmentId(): int
    {
        return $this->assessment->getId();
    }

    /**
     * @return DataModel|IAssessable
     */
    public function getAssessmentModel(): DataModel|IAssessable
    {
        return $this->assessment;
    }

    /**
     * @param DataModel|IAssessable $assessment
     * @return Model
     */
    public function setAssessmentModel(DataModel|IAssessable $assessment): self
    {
        $this->assessment = $assessment;
        return $this;
    }
}
