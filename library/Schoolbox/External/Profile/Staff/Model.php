<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Profile\Staff;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\External\Profile\Model as ProfileModel;

class Model
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /**
     * @var string|null
     */
    private ?string $username = null;


    /**
     * @var string|null
     */
    private ?string $email = null;


    /**
     * @var string|null
     */
    private ?string $category = null;


    /**
     * @var string|null
     */
    private ?string $positionTitle = null;


    /**
     * @var string|null
     */
    private ?string $department = null;


    /**
     * @var string|null
     */
    private ?string $phone = null;


    /**
     * @var string|null
     */
    private ?string $room = null;


    /**
     * @var string|null
     */
    private ?string $building = null;


    /**
     * @var string|null
     */
    private ?string $campusName = null;


    /**
     * @var string|null
     */
    private ?string $house = null;


    /**
     * @var ProfileModel|null
     */
    private ?ProfileModel $baseProfile = null;


    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }


    /**
     * @param string|null $username
     * @return Model
     */
    public function setUsername(?string $username): self
    {
        $this->username = $username;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }


    /**
     * @param string|null $email
     * @return Model
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }


    /**
     * @param string|null $category
     * @return Model
     */
    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getPositionTitle(): ?string
    {
        return $this->positionTitle;
    }


    /**
     * @param string|null $positionTitle
     * @return Model
     */
    public function setPositionTitle(?string $positionTitle): self
    {
        $this->positionTitle = $positionTitle;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getDepartment(): ?string
    {
        return $this->department;
    }


    /**
     * @param string|null $department
     * @return Model
     */
    public function setDepartment(?string $department): self
    {
        $this->department = $department;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }


    /**
     * @param string|null $phone
     * @return Model
     */
    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getRoom(): ?string
    {
        return $this->room;
    }


    /**
     * @param string|null $room
     * @return Model
     */
    public function setRoom(?string $room): self
    {
        $this->room = $room;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getBuilding(): ?string
    {
        return $this->building;
    }


    /**
     * @param string|null $building
     * @return Model
     */
    public function setBuilding(?string $building): self
    {
        $this->building = $building;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getCampusName(): ?string
    {
        return $this->campusName;
    }


    /**
     * @param string|null $campusName
     * @return Model
     */
    public function setCampusName(?string $campusName): self
    {
        $this->campusName = $campusName;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getHouse(): ?string
    {
        return $this->house;
    }


    /**
     * @param string|null $house
     * @return $this
     */
    public function setHouse(?string $house): self
    {
        $this->house = $house;
        return $this;
    }


    /**
     * @return ProfileModel
     */
    public function getBaseProfile(): ProfileModel
    {
        return $this->baseProfile;
    }


    /**
     * @param ProfileModel|null $baseProfile
     * @return Model
     */
    public function setBaseProfile(?ProfileModel $baseProfile): self
    {
        $this->baseProfile = $baseProfile;
        return $this;
    }
}
