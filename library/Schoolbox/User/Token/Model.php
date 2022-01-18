<?php

/**
 * Model for user JWT ID in Schoolbox.
 *
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\User\Token;

use Alaress_Date_Format;
use Schoolbox\User;

class Model
{
    /**
     * @var string|null
     */
    protected ?string $jwtId = null;

    /**
     * @var User|null
     */
    protected ?User $user = null;

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return self
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @var Alaress_Date_Format|null
     */
    protected ?Alaress_Date_Format $expiry = null;

    /**
     * @return string|null
     */
    public function getJwtId(): ?string
    {
        return $this->jwtId;
    }

    /**
     * @param string $jwtId
     * @return self
     */
    public function setJwtId(string $jwtId): self
    {
        $this->jwtId = $jwtId;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getExpiry(): ?Alaress_Date_Format
    {
        return $this->expiry;
    }

    /**
     * @param Alaress_Date_Format|null $expiry
     * @return self
     */
    public function setExpiry(?Alaress_Date_Format $expiry): self
    {
        $this->expiry = $expiry;
        return $this;
    }
}
