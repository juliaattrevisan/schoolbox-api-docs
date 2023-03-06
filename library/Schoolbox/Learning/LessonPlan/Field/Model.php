<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\LessonPlan\Field;

use Schoolbox\Admin\LessonPlan\Template\Field\Model as LessonPlanTemplateField;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * A lesson plan template field
     */
    private ?LessonPlanTemplateField $lessonPlanTemplateField = null;


    /**
     * The body text for a field
     *
     * @var string|null
     */
    private ?string $body = null;


    /**
     * Visibility in the list screen
     * Corresponds to Role::STUDENT or Role::STAFF
     *
     * @var string|null
     */
    private ?string $visibility = null;


    /**
     * An ordering field
     *
     * @var int|null
     */
    private ?int $sequence = null;


    /**
     * @return LessonPlanTemplateField|null
     */
    public function getLessonPlanTemplateField(): ?LessonPlanTemplateField
    {
        return $this->lessonPlanTemplateField;
    }


    /**
     * @param LessonPlanTemplateField|null $lessonPlanTemplateField
     * @return Model
     */
    public function setLessonPlanTemplateField(?LessonPlanTemplateField $lessonPlanTemplateField): self
    {
        $this->lessonPlanTemplateField = $lessonPlanTemplateField;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->lessonPlanTemplateField->getTitle() ?? null;
    }


    /**
     * @return string|null
     */
    public function getBody(): ?string
    {
        return $this->body;
    }


    /**
     * @param string|null $body
     * @return Model
     */
    public function setBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getVisibility(): ?string
    {
        return $this->lessonPlanTemplateField->getVisibility() ?? null;
    }


    /**
     * @return int|null
     */
    public function getSequence(): ?int
    {
        return $this->lessonPlanTemplateField->getSequence() ?? null;
    }
}
