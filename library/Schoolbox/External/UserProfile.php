<?php
/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
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
     * @var int
     */
    protected $user_id;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Alaress_Date_Format
     */
    protected $dob;

    /**
     * @var string
     */
    protected $level;

    /**
     * @var string
     */
    protected $homegroup;

    /**
     * @var string
     */
    protected $house;

    /**
     * @var string
     */
    protected $guardian1_name;

    /**
     * @var string
     */
    protected $guardian1_home_phone;

    /**
     * @var string
     */
    protected $guardian1_work_phone;

    /**
     * @var string
     */
    protected $guardian1_mobile_phone;

    /**
     * @var string
     */
    protected $guardian1_address;

    /**
     * @var string
     */
    protected $guardian1_suburb;

    /**
     * @var string
     */
    protected $guardian1_postcode;

    /**
     * @var string
     */
    protected $guardian1_email;

    /**
     * @var string
     */
    protected $guardian1_mail_address;

    /**
     * @var string
     */
    protected $guardian2_name;

    /**
     * @var string
     */
    protected $guardian2_home_phone;

    /**
     * @var string
     */
    protected $guardian2_work_phone;

    /**
     * @var string
     */
    protected $guardian2_mobile_phone;

    /**
     * @var string
     */
    protected $guardian2_address;

    /**
     * @var string
     */
    protected $guardian2_suburb;

    /**
     * @var string
     */
    protected $guardian2_postcode;

    /**
     * @var string
     */
    protected $guardian2_email;

    /**
     * @var string
     */
    protected $guardian2_mail_address;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $given_name;

    /**
     * @var string
     */
    protected $preferred_name;

    /**
     * @var string
     */
    protected $last_name;

    /**
     * @var string
     */
    protected $gender;

    /**
     * @var string
     */
    protected $email;

    /**
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    /**
     * @param int $user_id
     * @return static
     */
    public function setUserId(int $user_id): self
    {
        $this->user_id = $user_id;
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
     * @param Alaress_Date_Format $dob
     * @return static
     */
    public function setDob(Alaress_Date_Format $dob): self
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
     * @param string $level
     * @return static
     */
    public function setLevel(string $level): self
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
     * @param string $homegroup
     * @return static
     */
    public function setHomegroup(string $homegroup): self
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
     * @param string $house
     * @return static
     */
    public function setHouse(string $house): self
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
     * @param string $guardian1_name
     * @return static
     */
    public function setGuardian1Name(string $guardian1_name): self
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
     * @param string $guardian1_home_phone
     * @return static
     */
    public function setGuardian1HomePhone(string $guardian1_home_phone): self
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
     * @param string $guardian1_work_phone
     * @return static
     */
    public function setGuardian1WorkPhone(string $guardian1_work_phone): self
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
     * @param string $guardian1_mobile_phone
     * @return static
     */
    public function setGuardian1MobilePhone(string $guardian1_mobile_phone): self
    {
        $this->guardian1_mobile_phone = $guardian1_mobile_phone;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian1Address(): ?string
    {
        return $this->guardian1_address;
    }

    /**
     * @param string $guardian1_address
     * @return static
     */
    public function setGuardian1Address(string $guardian1_address): self
    {
        $this->guardian1_address = $guardian1_address;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian1Suburb(): ?string
    {
        return $this->guardian1_suburb;
    }

    /**
     * @param string $guardian1_suburb
     * @return static
     */
    public function setGuardian1Suburb(string $guardian1_suburb): self
    {
        $this->guardian1_suburb = $guardian1_suburb;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian1Postcode(): ?string
    {
        return $this->guardian1_postcode;
    }

    /**
     * @param string $guardian1_postcode
     * @return static
     */
    public function setGuardian1Postcode(string $guardian1_postcode): self
    {
        $this->guardian1_postcode = $guardian1_postcode;
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
     * @param string $guardian1_email
     * @return static
     */
    public function setGuardian1Email(string $guardian1_email): self
    {
        $this->guardian1_email = $guardian1_email;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian1MailAddress(): ?string
    {
        return $this->guardian1_mail_address;
    }

    /**
     * @param string $guardian1_mail_address
     * @return static
     */
    public function setGuardian1MailAddress(string $guardian1_mail_address): self
    {
        $this->guardian1_mail_address = $guardian1_mail_address;
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
     * @param string $guardian2_name
     * @return static
     */
    public function setGuardian2Name(string $guardian2_name): self
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
     * @param string $guardian2_home_phone
     * @return static
     */
    public function setGuardian2HomePhone(string $guardian2_home_phone): self
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
     * @param string $guardian2_work_phone
     * @return static
     */
    public function setGuardian2WorkPhone(string $guardian2_work_phone): self
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
     * @param string $guardian2_mobile_phone
     * @return static
     */
    public function setGuardian2MobilePhone(string $guardian2_mobile_phone): self
    {
        $this->guardian2_mobile_phone = $guardian2_mobile_phone;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian2Address(): ?string
    {
        return $this->guardian2_address;
    }

    /**
     * @param string $guardian2_address
     * @return static
     */
    public function setGuardian2Address(string $guardian2_address): self
    {
        $this->guardian2_address = $guardian2_address;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian2Suburb(): ?string
    {
        return $this->guardian2_suburb;
    }

    /**
     * @param string $guardian2_suburb
     * @return static
     */
    public function setGuardian2Suburb(string $guardian2_suburb): self
    {
        $this->guardian2_suburb = $guardian2_suburb;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian2Postcode(): ?string
    {
        return $this->guardian2_postcode;
    }

    /**
     * @param string $guardian2_postcode
     * @return static
     */
    public function setGuardian2Postcode(string $guardian2_postcode): self
    {
        $this->guardian2_postcode = $guardian2_postcode;
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
     * @param string $guardian2_email
     * @return static
     */
    public function setGuardian2Email(string $guardian2_email): self
    {
        $this->guardian2_email = $guardian2_email;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getGuardian2MailAddress(): ?string
    {
        return $this->guardian2_mail_address;
    }

    /**
     * @param string $guardian2_mail_address
     * @return static
     */
    public function setGuardian2MailAddress(string $guardian2_mail_address): self
    {
        $this->guardian2_mail_address = $guardian2_mail_address;
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
     * @param string $title
     * @return static
     */
    public function setTitle(string $title): self
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
     * @param string $given_name
     * @return static
     */
    public function setGivenName(string $given_name): self
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
     * @param string $preferred_name
     * @return static
     */
    public function setPreferredName(string $preferred_name): self
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
     * @param string $last_name
     * @return static
     */
    public function setLastName(string $last_name): self
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
     * @param string $gender
     * @return static
     */
    public function setGender(string $gender): self
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
     * @param string $email
     * @return static
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }
}
