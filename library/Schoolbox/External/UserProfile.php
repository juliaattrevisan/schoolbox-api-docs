<?php

/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External;

use Alaress_Date_Format;
use Schoolbox\User;

/**
 * User profile object for Schoolbox.
 */
class UserProfile
{
    /**
     * @var User
     */
    private $user;

    /**
     * @var Alaress_Date_Format|null
     */
    private $dob;

    /**
     * @var string|null
     */
    private $level;

    /**
     * @var string|null
     */
    private $homegroup;

    /**
     * @var string|null
     */
    private $house;

    /**
     * @var string|null
     */
    private $guardian1_name;

    /**
     * @var string|null
     */
    private $guardian1_home_phone;

    /**
     * @var string|null
     */
    private $guardian1_work_phone;

    /**
     * @var string|null
     */
    private $guardian1_mobile_phone;

    /**
     * @var string|null
     */
    private $guardian1_email;

    /**
     * @var string|null
     */
    private $guardian2_name;

    /**
     * @var string|null
     */
    private $guardian2_home_phone;

    /**
     * @var string|null
     */
    private $guardian2_work_phone;

    /**
     * @var string|null
     */
    private $guardian2_mobile_phone;

    /**
     * @var string|null
     */
    private $guardian2_email;

    /**
     * @var string|null
     */
    private $title;

    /**
     * @var string|null
     */
    private $given_name;

    /**
     * @var string|null
     */
    private $preferred_name;

    /**
     * @var string|null
     */
    private $last_name;

    /**
     * @var string|null
     */
    private $gender;

    /**
     * @var string|null
     */
    private $email;

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return static
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getDob(): ?Alaress_Date_Format
    {
        return $this->dob;
    }

    /**
     * @param Alaress_Date_Format|null $dob
     * @return static
     */
    public function setDob(?Alaress_Date_Format $dob): self
    {
        $this->dob = $dob;
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
     * @return static
     */
    public function setLevel(?string $level): self
    {
        $this->level = $level;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getHomegroup(): ?string
    {
        return $this->homegroup;
    }

    /**
     * @param string|null $homegroup
     * @return static
     */
    public function setHomegroup(?string $homegroup): self
    {
        $this->homegroup = $homegroup;
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
     * @return static
     */
    public function setHouse(?string $house): self
    {
        $this->house = $house;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian1Name(): ?string
    {
        return $this->guardian1_name;
    }

    /**
     * @param string|null $guardian1_name
     * @return static
     */
    public function setGuardian1Name(?string $guardian1_name): self
    {
        $this->guardian1_name = $guardian1_name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian1HomePhone(): ?string
    {
        return $this->guardian1_home_phone;
    }

    /**
     * @param string|null $guardian1_home_phone
     * @return static
     */
    public function setGuardian1HomePhone(?string $guardian1_home_phone): self
    {
        $this->guardian1_home_phone = $guardian1_home_phone;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian1WorkPhone(): ?string
    {
        return $this->guardian1_work_phone;
    }

    /**
     * @param string|null $guardian1_work_phone
     * @return static
     */
    public function setGuardian1WorkPhone(?string $guardian1_work_phone): self
    {
        $this->guardian1_work_phone = $guardian1_work_phone;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian1MobilePhone(): ?string
    {
        return $this->guardian1_mobile_phone;
    }

    /**
     * @param string|null $guardian1_mobile_phone
     * @return static
     */
    public function setGuardian1MobilePhone(?string $guardian1_mobile_phone): self
    {
        $this->guardian1_mobile_phone = $guardian1_mobile_phone;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian1Email(): ?string
    {
        return $this->guardian1_email;
    }

    /**
     * @param string|null $guardian1_email
     * @return static
     */
    public function setGuardian1Email(?string $guardian1_email): self
    {
        $this->guardian1_email = $guardian1_email;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian2Name(): ?string
    {
        return $this->guardian2_name;
    }

    /**
     * @param string|null $guardian2_name
     * @return static
     */
    public function setGuardian2Name(?string $guardian2_name): self
    {
        $this->guardian2_name = $guardian2_name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian2HomePhone(): ?string
    {
        return $this->guardian2_home_phone;
    }

    /**
     * @param string|null $guardian2_home_phone
     * @return static
     */
    public function setGuardian2HomePhone(?string $guardian2_home_phone): self
    {
        $this->guardian2_home_phone = $guardian2_home_phone;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian2WorkPhone(): ?string
    {
        return $this->guardian2_work_phone;
    }

    /**
     * @param string|null $guardian2_work_phone
     * @return static
     */
    public function setGuardian2WorkPhone(?string $guardian2_work_phone): self
    {
        $this->guardian2_work_phone = $guardian2_work_phone;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian2MobilePhone(): ?string
    {
        return $this->guardian2_mobile_phone;
    }

    /**
     * @param string|null $guardian2_mobile_phone
     * @return static
     */
    public function setGuardian2MobilePhone(?string $guardian2_mobile_phone): self
    {
        $this->guardian2_mobile_phone = $guardian2_mobile_phone;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian2Email(): ?string
    {
        return $this->guardian2_email;
    }

    /**
     * @param string|null $guardian2_email
     * @return static
     */
    public function setGuardian2Email(?string $guardian2_email): self
    {
        $this->guardian2_email = $guardian2_email;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string|null $title
     * @return static
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGivenName(): ?string
    {
        return $this->given_name;
    }

    /**
     * @param string|null $given_name
     * @return static
     */
    public function setGivenName(?string $given_name): self
    {
        $this->given_name = $given_name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPreferredName(): ?string
    {
        return $this->preferred_name;
    }

    /**
     * @param string|null $preferred_name
     * @return static
     */
    public function setPreferredName(?string $preferred_name): self
    {
        $this->preferred_name = $preferred_name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    /**
     * @param string|null $last_name
     * @return static
     */
    public function setLastName(?string $last_name): self
    {
        $this->last_name = $last_name;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGender(): ?string
    {
        return $this->gender;
    }

    /**
     * @param string|null $gender
     * @return static
     */
    public function setGender(?string $gender): self
    {
        $this->gender = $gender;
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
     * @return static
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }
}
