<?php

/**
 * IB Subjects Model
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Subject;

use JsonSerializable;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Class Model
 * Represents an IB Subject
 *
 * @package Schoolbox\IB\Subject
 */
class Model implements JsonSerializable
{
    use Id;

    /**
     * Subject Name
     * @var string|null
     */
    private ?string $name = null;

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     * @return self
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
        ];
    }
}
