<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Template;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Sequential;

class Model implements ISoftDeletable, Sequential
{
    use ModelTrait\Id;
    use ModelTrait\DeletedAtDate;
    use ModelTrait\HasSequence;

    private string $name;

    private HomepageTemplate $courseTemplate;

    private HomepageTemplate $unitTemplate;

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
}
