<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Profile\Relation;

class Model
{
    /**
     * @var int
     */
    private $childProfileId;

    /**
     * @var int
     */
    private $userProfileId;

    /**
     * @var bool
     */
    private $emergencyContact;

    /**
     * @var string
     */
    private $type;

    /**
     * @return int
     */
    public function getChildProfileId(): int
    {
        return $this->childProfileId;
    }

    /**
     * @return int
     */
    public function getUserProfileId(): int
    {
        return $this->userProfileId;
    }

    /**
     * @return bool
     */
    public function getEmergencyContact(): bool
    {
        return $this->emergencyContact;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }


    /**
     * @param int $childProfileId
     * @return Model
     */
    public function setChildProfileId(int $childProfileId): self
    {
        $this->childProfileId = $childProfileId;
        return $this;
    }

    /**
     * @param int $userProfileId
     * @return Model
     */
    public function setUserProfileId(int $userProfileId): self
    {
        $this->userProfileId = $userProfileId;
        return $this;
    }

    /**
     * @param bool $emergencyContact
     * @return Model
     */
    public function setEmergencyContact(bool $emergencyContact): self
    {
        $this->emergencyContact = $emergencyContact;
        return $this;
    }

    /**
     * @param string $type
     * @return Model
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }
}
