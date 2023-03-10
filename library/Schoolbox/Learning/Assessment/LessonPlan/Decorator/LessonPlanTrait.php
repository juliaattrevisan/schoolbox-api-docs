<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\LessonPlan\Decorator;

use Schoolbox\Learning\Assessment\Factory;

trait LessonPlanTrait
{
    /**
     * @var int|null
     */
    private ?int $lessonPlanTemplateId = null;


    /**
     * @var array
     */
    private ?array $lessonPlanFields = null;


    /**
     * Whether this Lesson Plan is visible to students or just staff
     *
     * @var string|null
     */
    private ?string $lessonPlanVisibility = null;


    /**
     * @return int|null
     */
    public function getLessonPlanTemplateId(): ?int
    {
        return $this->lessonPlanTemplateId;
    }


    /**
     * @param int|null $lessonPlanTemplateId
     * @return Factory|LessonPlanTrait
     */
    public function setLessonPlanTemplateId(?int $lessonPlanTemplateId): self
    {
        $this->lessonPlanTemplateId = $lessonPlanTemplateId;
        return $this;
    }


    /**
     * @return array|null
     */
    public function getLessonPlanFields(): ?array
    {
        return $this->lessonPlanFields;
    }


    /**
     * @param array|null $lessonPlanFields
     * @return Factory|LessonPlanTrait
     */
    public function setLessonPlanFields(?array $lessonPlanFields): self
    {
        $this->lessonPlanFields = $lessonPlanFields;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getLessonPlanVisibility(): ?string
    {
        return $this->lessonPlanVisibility;
    }


    /**
     * @param string|null $lessonPlanVisibility
     * @return Factory|LessonPlanTrait
     */
    public function setLessonPlanVisibility(?string $lessonPlanVisibility): self
    {
        $this->lessonPlanVisibility = $lessonPlanVisibility;
        return $this;
    }
}
