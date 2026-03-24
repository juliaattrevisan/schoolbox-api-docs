<?php

/**
 * IB Group-Subjects Junction Model
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\GroupSubject;

use JsonSerializable;

/**
 * Class Model
 * Represents the relationship between IB Groups and Subjects with levels
 *
 * @package Schoolbox\IB\GroupSubject
 */
class Model implements JsonSerializable
{
    /**
     * Junction ID
     * @var int|null
     */
    private ?int $id = null;

    /**
     * IB Group ID
     * @var int|null
     */
    private ?int $groupId = null;

    /**
     * IB Subject ID
     * @var int|null
     */
    private ?int $subjectId = null;

    /**
     * Subject Level (SL or HL)
     * @var string|null
     */
    private ?string $level = null;

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
     * @return int|null
     */
    public function getGroupId(): ?int
    {
        return $this->groupId;
    }

    /**
     * @param int|null $groupId
     * @return self
     */
    public function setGroupId(?int $groupId): self
    {
        $this->groupId = $groupId;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getSubjectId(): ?int
    {
        return $this->subjectId;
    }

    /**
     * @param int|null $subjectId
     * @return self
     */
    public function setSubjectId(?int $subjectId): self
    {
        $this->subjectId = $subjectId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLevel(): ?string
    {
        return $this->level;
    }

    /**
     * @param string|null $level
     * @return self
     */
    public function setLevel(?string $level): self
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'groupId' => $this->getGroupId(),
            'subjectId' => $this->getSubjectId(),
            'level' => $this->getLevel(),
        ];
    }
}
