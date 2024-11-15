<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\BulkImport\Access;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Pastoral\BulkImport\Model as BulkImportModel;
use Schoolbox\Pastoral\Group\Model as Group;
use Schoolbox\Pastoral\IPastoralAccess;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Model for pastoral bulk import access records.
 *
 * Used as a template for adding access records to the pastoral records the bulk
 * import process creates.
 */
class Model implements IPastoralAccess
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;

    /**
     * The bulk import this access is associated with.
     */
    private BulkImportModel $pastoralBulkImport;

    /**
     * The pastoral group (if any) this access is associated with.
     */
    private ?Group $group = null;

    /**
     * The role (if any) this access is associated with.
     */
    private ?Role $role = null;

    /**
     * The role type (if any) this access is associated with.
     *
     * @var Role::STUDENT|Role::STAFF|Role::PARENT|Role::GUEST|null
     */
    private ?string $roleType = null;

    /**
     * The user (if any) this access is associated with.
     */
    private ?User $user = null;

    /**
     * The level of allowed access.
     *
     * @var PastoralAccessMapper::ACCESS_*
     */
    private int $access;

    /**
     * Whether role / group access is limited to users in student campus only
     */
    private bool $matchCampus = false;

    /**
     * Whether the user/role is allowed to comment
     * Staff with access can always comment regardless of this flag
     */
    private bool $commentsAllowed = false;

    /**
     * @return BulkImportModel
     */
    public function getPastoralBulkImport(): BulkImportModel
    {
        return $this->pastoralBulkImport;
    }

    /**
     * @param BulkImportModel $pastoralBulkImport
     * @return $this
     */
    public function setPastoralBulkImport(BulkImportModel $pastoralBulkImport): self
    {
        $this->pastoralBulkImport = $pastoralBulkImport;
        return $this;
    }

    /**
     * @return Group|null
     */
    public function getGroup(): ?Group
    {
        return $this->group;
    }

    /**
     * @param Group|null $group
     * @return $this
     */
    public function setGroup(?Group $group): self
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return Role|null
     */
    public function getRole(): ?Role
    {
        return $this->role;
    }

    /**
     * @param Role|null $role
     * @return $this
     */
    public function setRole(?Role $role): self
    {
        $this->role = $role;
        return $this;
    }

    /**
     * @return Role::STUDENT|Role::STAFF|Role::PARENT|Role::GUEST|null
     */
    public function getRoleType(): ?string
    {
        return $this->roleType;
    }

    /**
     * @param Role::STUDENT|Role::STAFF|Role::PARENT|Role::GUEST|null $roleType
     * @return $this
     */
    public function setRoleType(?string $roleType): self
    {
        $this->roleType = $roleType;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return PastoralAccessMapper::ACCESS_*
     */
    public function getAccess(): int
    {
        return $this->access;
    }

    /**
     * @param PastoralAccessMapper::ACCESS_* $access
     * @return $this
     */
    public function setAccess(int $access): self
    {
        $this->access = $access;
        return $this;
    }

    /**
     * @return bool
     */
    public function getMatchCampus(): bool
    {
        return $this->matchCampus;
    }

    /**
     * @param bool $matchCampus
     * @return $this
     */
    public function setMatchCampus(bool $matchCampus): self
    {
        $this->matchCampus = $matchCampus;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCommentsAllowed(): bool
    {
        return $this->commentsAllowed;
    }

    /**
     * @param bool $commentsAllowed
     * @return $this
     */
    public function setCommentsAllowed(bool $commentsAllowed): self
    {
        $this->commentsAllowed = $commentsAllowed;
        return $this;
    }

    /**
     * Update `$model` with the fields in this object, with the intent of
     * saving them to a pastoral record.
     *
     * @param PastoralAccessModel $model
     * @return PastoralAccessModel
     */
    public function writeToPastoralAccess(PastoralAccessModel $model): PastoralAccessModel
    {
        return $model
            ->setAccess($this->access)
            ->setCommentsAllowed($this->commentsAllowed)
            ->setMatchCampus($this->matchCampus)
            ->setGroup($this->group ? (clone $this->group) : null)
            ->setRole($this->role ? (clone $this->role) : null)
            ->setRoleType($this->roleType)
            ->setUser($this->user ? (clone $this->user) : null)
        ;
    }
}
