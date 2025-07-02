<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

 declare(strict_types=1);

namespace Schoolbox\Admin\Editor\Templates\Category;

use Schoolbox\Mapper\Traits\Model\Id;

class Model
{
    public const BADGE_EVIDENCE = 1;
    public const CALENDAR_EVENTS = 2;
    public const EMAIL = 3;
    public const FEEDBACK = 4;
    public const HOMEPAGE = 5;
    public const LEARNING_ACTIVITY = 6;
    public const LEARNING_MOMENTS = 7;
    public const NEWS = 8;

    use Id;

    /**
     * @var string
     */
    private string $name;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
