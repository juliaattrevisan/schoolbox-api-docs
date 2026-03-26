<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Config\Field;

use Schoolbox\Mapper\Traits\Model\Id;

class Model
{
    use Id;

    private string $field;

    /**
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @param string $field
     * @return self
     */
    public function setField(string $field): self
    {
        $this->field = $field;
        return $this;
    }
}
