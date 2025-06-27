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
