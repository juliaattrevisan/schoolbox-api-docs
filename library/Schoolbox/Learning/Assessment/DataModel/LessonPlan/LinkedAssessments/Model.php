<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan\LinkedAssessments;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlanModel;

class Model
{
    /**
     * Lesson Plan id
     */
    private int $lessonPlanId;
    /**
     * Lesson Plan
     */
    private  $lessonPlan;

    /**
     * Linked Assessment ID
     */
    private int $assessmentId;

    /**
     * Linked Assessment
     */
    private IAssessable $assessment;

    /**
     * @param int $lessonPlanId
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
     * @return LessonPlanModel
     */
    public function getLessonPlan(): LessonPlanModel
    {
        return $this->lessonPlan;
    }

    /**
     * @param $lessonPlan
     */
    public function setLessonPlan( $lessonPlan): self
    {
        $this->lessonPlanId = $lessonPlan->getId();
        $this->lessonPlan = $lessonPlan;
        return $this;
    }

    /**
     * @param int $assessmentId
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
     * @return IAssessable
     */
    public function getAssessmentModel(): IAssessable
    {
        return $this->assessment;
    }

    /**
     * @param IAssessable $assessment
     */
    public function setAssessmentModel(IAssessable $assessment): self
    {
        $this->assessmentId = $assessment->getId();
        $this->assessment = $assessment;
        return $this;
    }
}
