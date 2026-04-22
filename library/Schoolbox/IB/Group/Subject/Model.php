<?php

/**
 * IB Group-Subjects Junction Model
 * @author    Haris Ali
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Group\Subject;

use JsonSerializable;
use Schoolbox\GenericCollection;
use Schoolbox\IB\Group\Subject\Language\Model as GroupSubjectLanguageModel;
use Schoolbox\IB\Group\Model as GroupModel;
use Schoolbox\IB\Subject\Model as SubjectModel;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Class Model
 * Represents the relationship between IB Groups and Subjects with levels
 *
 * @package Schoolbox\IB\GroupSubject
 */
class Model implements JsonSerializable
{
    use Id;

    /**
     * IB Group ID
     * @var int|null
     */
    private ?int $groupId = null;

    /**
     * Subject Level (SL or HL)
     * @var string|null
     */
    private ?string $level = null;

    /**
     * @var SubjectModel|null
     */
    private ?SubjectModel $subject = null;

    /**
     * @var GroupModel|null
     */
    private ?GroupModel $group = null;

    /**
     * @var GenericCollection|GroupSubjectLanguageModel[]|null
     */
    private ?GenericCollection $languages = null;

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
     * @return SubjectModel|null
     */
    public function getSubject(): ?SubjectModel
    {
        return $this->subject;
    }

    /**
     * @param SubjectModel|null $subject
     * @return self
     */
    public function setSubject(?SubjectModel $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * @return GroupModel|null
     */
    public function getGroup(): ?GroupModel
    {
        return $this->group;
    }

    /**
     * @param GroupModel|null $group
     * @return self
     */
    public function setGroup(?GroupModel $group): self
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @param GenericCollection $languages
     * @return $this
     */
    public function setLanguages(GenericCollection $languages): self
    {
        $languages->assertCollectionOf(GroupSubjectLanguageModel::class);
        $this->languages = $languages;
        return $this;
    }

    /**
     * @return GenericCollection
     */
    public function getLanguages(): GenericCollection
    {
        return $this->languages;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'groupId' => $this->getGroupId(),
            'subjectId' => $this->getSubject()?->getId(),
            'level' => $this->getLevel(),
        ];
    }
}
