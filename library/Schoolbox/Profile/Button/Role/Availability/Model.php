<?php

declare(strict_types=1);

namespace Schoolbox\Profile\Button\Role\Availability;

class Model
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $profileButtonId;

    /** @var int */
    protected $roleId;

    /**
     * @param int $id
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return nulL|int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param int $profileButtonId
     * @return self
     */
    public function setProfileButtonId(int $profileButtonId): self
    {
        $this->profileButtonId = $profileButtonId;
        return $this;
    }

    /**
     * @return int
     */
    public function getProfileButtonId(): int
    {
        return $this->profileButtonId;
    }

    /**
     * @param int $roleId
     * @return self
     */
    public function setRoleId(int $roleId): self
    {
        $this->roleId = $roleId;
        return $this;
    }

    /**
     * @return int
     */
    public function getRoleId(): int
    {
        return $this->roleId;
    }
}
