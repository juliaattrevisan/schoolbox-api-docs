<?php

declare(strict_types=1);

namespace Schoolbox\Profile\Button\Campus\Visibility;

class Model
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $profileButtonId;

    /** @var int */
    protected $campusId;

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
     * @return null|int
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
     * @param int $campusId
     * @return self
     */
    public function setCampusId(int $campusId): self
    {
        $this->campusId = $campusId;
        return $this;
    }

    /**
     * @return int
     */
    public function getCampusId(): int
    {
        return $this->campusId;
    }
}
