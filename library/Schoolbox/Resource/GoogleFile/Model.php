<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\GoogleFile;

use Alaress\Json\SerializeTrait;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use DeletedAtDate;
    use SerializeTrait;

    protected string $googleId;

    protected int $folderId;

    protected int $ownerId;

    /**
     * @return string
     */
    public function getGoogleId(): string
    {
        return $this->googleId;
    }

    /**
     * @param string $googleId
     * @return static
     */
    public function setGoogleId(string $googleId): self
    {
        $this->googleId = $googleId;
        return $this;
    }

    /**
     * @return int
     */
    public function getFolderId(): int
    {
        return $this->folderId;
    }

    /**
     * @param int $folderId
     * @return static
     */
    public function setFolderId(int $folderId): self
    {
        $this->folderId = $folderId;
        return $this;
    }

    /**
     * @return int
     */
    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    /**
     * @param int $ownerId
     * @return static
     */
    public function setOwnerId(int $ownerId): self
    {
        $this->ownerId = $ownerId;
        return $this;
    }
}
