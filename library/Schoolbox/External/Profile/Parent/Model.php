<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Profile\Parent;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\External\Profile\Model as ProfileModel;

class Model
{
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /**
     * @var string|null
     */
    private $mobile;

    /**
     * @var string|null
     */
    private $phoneHome;

    /**
     * @var string|null
     */
    private $phoneWork;

    /**
     * @var string|null
     */
    private $username;

    /**
     * @var string|null
     */
    private $email;

    /**
     * @var ProfileModel
     */
    private $baseProfile;


    /**
     * @return string|null
     */
    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    /**
     * @return string|null
     */
    public function getPhoneHome(): ?string
    {
        return $this->phoneHome;
    }

    /**
     * @return string|null
     */
    public function getPhoneWork(): ?string
    {
        return $this->phoneWork;
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }


    /**
     * @param string $mobile
     * @return Model
     */
    public function setMobile(?string $mobile): self
    {
        $this->mobile = $mobile;
        return $this;
    }

    /**
     * @param string $phoneHome
     * @return Model
     */
    public function setPhoneHome(?string $phoneHome): self
    {
        $this->phoneHome = $phoneHome;
        return $this;
    }

    /**
     * @param string $phoneWork
     * @return Model
     */
    public function setPhoneWork(?string $phoneWork): self
    {
        $this->phoneWork = $phoneWork;
        return $this;
    }

    /**
     * @param string $username
     * @return Model
     */
    public function setUsername(?string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @param string $email
     * @return Model
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;
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
