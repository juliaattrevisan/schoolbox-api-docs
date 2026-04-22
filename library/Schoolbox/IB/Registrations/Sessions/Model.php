<?php

/**
 * IB Registration Session Model
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\IB\Registrations\Sessions;

use JsonSerializable;
use Schoolbox\IB\SystemPurpose\Model as SystemPurposeModel;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\User;

/**
 * Class Model
 *
 * @package Schoolbox\IB\Registrations\Sessions
 */
class Model implements ISoftDeletable, JsonSerializable
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;
    use ModelTraits\DeletedAtDate;

    /**
     * The system purpose this registration session belongs to
     * @var SystemPurposeModel|null
     */
    private ?SystemPurposeModel $systemPurpose = null;

    /**
     * Academic year label, e.g. 2026 or 2025/2026
     * @var string|null
     */
    private ?string $academicYear = null;

    /**
     * Examination session name
     * @var string|null
     */
    private ?string $examSession = null;

    /**
     * User who created this registration session
     * @var User|null
     */
    private ?User $createdBy = null;

    /**
     * User who last updated this registration session
     * @var User|null
     */
    private ?User $updatedBy = null;

    /**
     * User who deleted this registration session
     * @var User|null
     */
    private ?User $deletedBy = null;

    /**
     * @return SystemPurposeModel|null
     */
    public function getSystemPurpose(): ?SystemPurposeModel
    {
        return $this->systemPurpose;
    }

    /**
     * @param SystemPurposeModel|null $systemPurpose
     * @return self
     */
    public function setSystemPurpose(?SystemPurposeModel $systemPurpose): self
    {
        $this->systemPurpose = $systemPurpose;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAcademicYear(): ?string
    {
        return $this->academicYear;
    }

    /**
     * @param string|null $academicYear
     * @return self
     */
    public function setAcademicYear(?string $academicYear): self
    {
        $this->academicYear = $academicYear;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getExamSession(): ?string
    {
        return $this->examSession;
    }

    /**
     * @param string|null $examSession
     * @return self
     */
    public function setExamSession(?string $examSession): self
    {
        $this->examSession = $examSession;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * @param User|null $createdBy
     * @return self
     */
    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    /**
     * @param User|null $updatedBy
     * @return self
     */
    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }

    /**
     * @param User|null $deletedBy
     * @return self
     */
    public function setDeletedBy(?User $deletedBy): self
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'systemPurpose' => $this->getSystemPurpose(),
            'academicYear' => $this->getAcademicYear(),
            'examSession' => $this->getExamSession(),
            'createdAt' => $this->getCreatedAt(),
            'createdBy' => $this->getCreatedBy(),
            'updatedAt' => $this->getUpdatedAt(),
            'updatedBy' => $this->getUpdatedBy(),
            'deletedAt' => $this->getDeletedAt(),
            'deletedBy' => $this->getDeletedBy(),
        ];
    }
}
