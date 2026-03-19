<?php

/**
 * IB Group Model
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Group;

use JsonSerializable;

/**
 * Class Model
 * Represents an IB Group (Studies in Language and Literature, Language Acquisition, etc.)
 *
 * @package Schoolbox\IB\Group
 */
class Model implements JsonSerializable
{
    /**
     * Group ID
     * @var int|null
     */
    private ?int $id = null;

    /**
     * Group Name
     * @var string|null
     */
    private ?string $name = null;

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int|null $id
     * @return self
     */
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

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