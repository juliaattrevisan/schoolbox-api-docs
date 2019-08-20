<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\User\Relation;

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
    public function isEmergencyContact(): bool
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
     * @param string $childProfileId
     * @return Model
     */
    public function setChildProfileId(string $childProfileId): Model
    {
        $this->childProfileId = $childProfileId;
        return $this;
    }

    /**
     * @param string $userProfileId
     * @return Model
     */
    public function setUserProfileId(string $userProfileId): Model
    {
        $this->userProfileId = $userProfileId;
        return $this;
    }

    /**
     * @param bool $emergencyContact
     * @return Model
     */
    public function setEmergencyContact(bool $emergencyContact): Model
    {
        $this->emergencyContact = $emergencyContact;
        return $this;
    }

    /**
     * @param string $type
     * @return Model
     */
    public function setType(string $type): Model
    {
        $this->type = $type;
        return $this;
    }
}
