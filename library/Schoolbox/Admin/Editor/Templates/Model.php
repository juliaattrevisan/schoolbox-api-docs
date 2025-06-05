<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Editor\Templates;

use Schoolbox\Mapper\Traits\Model\Id;

class Model
{
    use Id;

    /**
     * The type this template is for.
     *
     * @var string|null
     */
    private ?string $type = null;

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string|null $type
     * @return Model
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }
}
