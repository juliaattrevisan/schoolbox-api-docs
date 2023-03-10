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
}
