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
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use DeletedAtDate;
    use CreatedAtDate;

    // default expiry days
    public const DEFAULT_EXPIRY = 60;

    public const TYPE_API = 'api';
    public const TYPE_QRCODE = 'qrcode';

    /**
     * @var string|null
     */
    protected ?string $jwtId = null;

    /**
     * @var User|null
     */
    protected ?User $user = null;

    /**
     * @var string|null
     */
    protected ?string $type = null;

    /**
     * @var User|null
     */
    protected ?User $createdBy = null;

    /**
     * @var User|null
     */
    protected ?User $deletedBy = null;


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
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string|null $type
     * @return self
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
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

    /**
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * @param User|null $createdBy
     *
     * @return $this
     */
    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getDeletedBy(): ?User
    {
        return $this->deletedBy;
    }

    /**
     * @param User|null $deletedBy
     *
     * @return $this
     */
    public function setDeletedBy(?User $deletedBy): self
    {
        $this->deletedBy = $deletedBy;
        return $this;
    }
}
