<?php

/**
 * @copyright   Copyright 2032, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Template;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Resource\HomepageTemplate;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\DeletedAtDate;

    private string $name;

    private HomepageTemplate $courseTemplate;

    private HomepageTemplate $unitTemplate;

    /**
     * Should newly created courses inherit folder perms from the parent?
     * @var bool
     */
    private bool $courseInherit;

    /**
     * Should newly created units inherit folder perms from the parent course?
     * @var bool
     */
    private bool $unitInherit;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param HomepageTemplate $courseTemplate
     * @return Model
     */
    public function setCourseTemplate(HomepageTemplate $courseTemplate): self
    {
        $this->courseTemplate = $courseTemplate;
        return $this;
    }

    /**
     * @return HomepageTemplate
     */
    public function getCourseTemplate(): HomepageTemplate
    {
        return $this->courseTemplate;
    }

    /**
     * @param HomepageTemplate $unitTemplate
     * @return Model
     */
    public function setUnitTemplate(HomepageTemplate $unitTemplate): self
    {
        $this->unitTemplate = $unitTemplate;
        return $this;
    }

    /**
     * @return HomepageTemplate
     */
    public function getUnitTemplate(): HomepageTemplate
    {
        return $this->unitTemplate;
    }

    /**
     * @param bool $courseInherit
     * @return self
     */
    public function setCourseInherit(bool $courseInherit): self
    {
        $this->courseInherit = $courseInherit;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCourseInherit(): bool
    {
        return $this->courseInherit;
    }

    /**
     * @param bool $unitInherit
     * @return Model
     */
    public function setUnitInherit(bool $unitInherit): self
    {
        $this->unitInherit = $unitInherit;
        return $this;
    }

    /**
     * @return bool
     */
    public function getUnitInherit(): bool
    {
        return $this->unitInherit;
    }
}
