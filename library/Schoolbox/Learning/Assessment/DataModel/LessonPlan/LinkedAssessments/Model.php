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
     * Lesson Plan id
     */
    private int $lessonPlanId;
    /**
     * Lesson Plan
     */
    private LessonPlanData $lessonPlan;

    /**
     * Linked Assessment ID
     */
    private int $assessmentId;

    /**
     * Linked Assessment
     */
    private DataModel|IAssessable $assessment;

    /**
     * @param int $lessonPlanId
     * @return Model
     */
    public function setLessonPlanId(int $lessonPlanId): self
    {
        $this->lessonPlanId = $lessonPlanId;
        return $this;
    }

    /**
     * @return int
     */
    public function getLessonPlanId(): int
    {
        return $this->lessonPlanId;
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
        $this->lessonPlanId = $lessonPlan->getId();
        $this->lessonPlan = $lessonPlan;
        return $this;
    }

    /**
     * @param int $assessmentId
     * @return Model
     */
    public function setAssessmentId(int $assessmentId): self
    {
        $this->assessmentId = $assessmentId;
        return $this;
    }

    /**
     * @return int
     */
    public function getAssessmentId(): int
    {
        return $this->assessmentId;
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
        $this->assessmentId = $assessment->getId();
        $this->assessment = $assessment;
        return $this;
    }
}
