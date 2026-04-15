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
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Class Model
 * Represents an IB Group (Studies in Language and Literature, Language Acquisition, etc.)
 *
 * @package Schoolbox\IB\Group
 */
class Model implements JsonSerializable
{
    use Id;

    /**
     * Group Name
     * @var string|null
     */
    private ?string $name = null;

    /**
     * Display sequence for ordering
     * @var int|null
     */
    private ?int $sequence = null;

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
     * @return int|null
     */
    public function getSequence(): ?int
    {
        return $this->sequence;
    }

    /**
     * @param int|null $sequence
     * @return self
     */
    public function setSequence(?int $sequence): self
    {
        $this->sequence = $sequence;
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
            'sequence' => $this->getSequence(),
        ];
    }
}
