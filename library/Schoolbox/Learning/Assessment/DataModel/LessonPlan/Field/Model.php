<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\LessonPlan\Field;

use Schoolbox\Admin\LessonPlan\Template\Field\Model as LessonPlanTemplateField;
use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Model as LessonPlan;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * The Lesson Plan to which this Lesson Plan Field is related
     *
     * @var LessonPlan|null
     */
    private ?LessonPlan $lessonPlan = null;


    /**
     * A Lesson Plan Template Field from which this instance originates
     */
    private ?int $lessonPlanTemplateField = null;


    /**
     * The title of the field
     *
     * @var string|null
     */
    private ?string $title = null;


    /**
     * A little extra text to help explain what this field is meant to provide.
     * It's used as an instructional to humans, but also, as extra data point
     * for an AI prompt to further refine its output
     *
     * @var string|null
     */
    private ?string $description = null;


    /**
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
     * The body text for a field
     *
     * @var string|null
     */
    private ?string $body = null;


    /**
     * @return LessonPlan|null
     */
    public function getLessonPlan(): ?LessonPlan
    {
        return $this->lessonPlan;
    }


    /**
     * @param LessonPlan|null $lessonPlan
     * @return Model
     */
    public function setLessonPlan(?LessonPlan $lessonPlan): self
    {
        $this->lessonPlan = $lessonPlan;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getLessonPlanTemplateField(): ?int
    {
        return $this->lessonPlanTemplateField;
    }


    /**
     * @param int|null $lessonPlanTemplateField
     * @return Model
     */
    public function setLessonPlanTemplateField(?int $lessonPlanTemplateField): self
    {
        $this->lessonPlanTemplateField = $lessonPlanTemplateField;
        return $this;
    }


    /**
     * Title is readonly in Lesson Plan Fields.
     *
     * This is derived from the original Lesson Plan Template Field,
     * but in the db they are copies of the Lesson Plan Template's value.
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }


    /**
     * @param string|null $title
     * @return Model
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }


    /**
     * @param string|null $description
     * @return $this
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
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
     * Visibility is readonly in Lesson Plan Fields.
     *
     * This is derived from the original Lesson Plan Template Field,
     * but in the db they are copies of the Lesson Plan Template's value.
     *
     * @return string|null
     */
    public function getVisibility(): ?string
    {
        return $this->visibility;
    }


    /**
     * @param string|null $visibility
     * @return Model
     */
    public function setVisibility(?string $visibility): self
    {
        $this->visibility = $visibility;
        return $this;
    }


    /**
     * Sequence is readonly in Lesson Plan Fields.
     *
     * This is derived from the original Lesson Plan Template Field,
     * but in the db they are copies of the Lesson Plan Template's value.
     *
     * @return int|null
     */
    public function getSequence(): ?int
    {
        return $this->sequence;
    }


    /**
     * @param int|null $sequence
     * @return Model
     */
    public function setSequence(?int $sequence): self
    {
        $this->sequence = $sequence;
        return $this;
    }
}
