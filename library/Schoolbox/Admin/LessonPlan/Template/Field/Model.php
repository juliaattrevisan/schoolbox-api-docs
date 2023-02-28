<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\LessonPlan\Template\Field;

use Schoolbox\Admin\LessonPlan\Template\Model as LessonPlanTemplate;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * A lesson plan template
     */
    private ?LessonPlanTemplate $lessonPlanTemplate = null;


    /**
     * The title of the field
     *
     * @var string|null
     */
    private ?string $title = null;


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
     * @return LessonPlanTemplate|null
     */
    public function getLessonPlanTemplate(): ?LessonPlanTemplate
    {
        return $this->lessonPlanTemplate;
    }


    /**
     * @param LessonPlanTemplate|null $lessonPlanTemplate
     * @return Model
     */
    public function setLessonPlanTemplate(?LessonPlanTemplate $lessonPlanTemplate): self
    {
        $this->lessonPlanTemplate = $lessonPlanTemplate;
        return $this;
    }


    /**
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
