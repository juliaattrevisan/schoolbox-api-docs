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

    /**
     * File Id fetched via Google API
     * @var string
     */
    protected string $googleId;

    /**
     * File name fetched via Google API
     * @var string
     */
    protected string $name;

    /**
     * File icon url fetched via Google API
     * @var string
     */
    protected string $icon;

    /**
     * File editable share link fetched via Google API
     * @var string
     */
    protected string $url;

    /**
     * Group access level allowed to edit
     * @var int
     */
    protected int $editLevel;

    /**
     * Group access level allowed to comment
     * @var int
     */
    protected int $commentLevel;

    /**
     * Folder id where this google file is shared
     * @var int
     */
    protected int $folderId;

    /**
     * User id who has associated this google file to this folder
     * @var int
     */
    protected int $ownerId;

    /**
     * Allowed access levels for editLevel and commentLevel
     * NOBODY is assigned to 4 rather than 0 to match strictness
     */
    public const LEVEL_NOBODY = 4;
    public const LEVEL_ADMIN  = 3;
    public const LEVEL_WRITE  = 2;
    public const LEVEL_READ   = 1;

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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return static
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * @param string $icon
     * @return static
     */
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return static
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
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
    public function getEditLevel(): int
    {
        return $this->editLevel;
    }

    /**
     * @param int $editLevel
     * @return static
     */
    public function setEditLevel(int $editLevel): self
    {
        $this->editLevel = $editLevel;
        return $this;
    }

    /**
     * @return int
     */
    public function getCommentLevel(): int
    {
        return $this->commentLevel;
    }

    /**
     * @param int $commentLevel
     * @return static
     */
    public function setCommentLevel(int $commentLevel): self
    {
        $this->commentLevel = $commentLevel;
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
