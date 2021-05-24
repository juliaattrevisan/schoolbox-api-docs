<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use JsonSerializable;

/**
 * Object representation of an attachment from Atlas Rubicon.
 */
class Attachment implements JsonSerializable
{
    public const ATTACHMENT_TYPE_LINK         = 'link';
    public const ATTACHMENT_TYPE_GOOGLE_DRIVE = 'googleDrive';
    public const ATTACHMENT_TYPE_FILE         = 'file';
    public const ATTACHMENT_TYPE_LESSON       = 'lesson';
    public const ATTACHMENT_TYPE_IMAGE        = 'image';
    public const ATTACHMENT_TYPE_ONEDRIVE     = 'oneDrive';

    /**
     * @var int
     */
    private $id;

    /**
     * @var null|string
     */
    private $title;

    /**
     * @var null|string
     */
    private $url;

    /**
     * @var int
     */
    private $sortOrder;

    /**
     * @var null|string
     */
    private $attachmentType;

    /**
     * @var null|string
     */
    private $itemType;

    /**
     * @var int
     */
    private $itemId;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId(int $id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     * @return static
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @param null|string $url
     * @return static
     */
    public function setUrl(?string $url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return int
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * @param int $sortOrder
     * @return static
     */
    public function setSortOrder(int $sortOrder)
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getAttachmentType(): ?string
    {
        return $this->attachmentType;
    }

    /**
     * @param null|string $attachmentType
     * @return static
     */
    public function setAttachmentType(?string $attachmentType)
    {
        $this->attachmentType = $attachmentType;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getItemType(): ?string
    {
        return $this->itemType;
    }

    /**
     * @param null|string $itemType
     * @return static
     */
    public function setItemType(?string $itemType)
    {
        $this->itemType = $itemType;
        return $this;
    }

    /**
     * @return int
     */
    public function getItemId(): int
    {
        return $this->itemId;
    }

    /**
     * @param int $itemId
     * @return static
     */
    public function setItemId(int $itemId)
    {
        $this->itemId = $itemId;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return [
            'id'             => $this->id,
            'title'          => $this->title,
            'url'            => $this->url,
            'sortOrder'      => $this->sortOrder,
            'itemType'       => $this->itemType,
            'itemId'         => $this->itemType,
            'attachmentType' => $this->attachmentType,
        ];
    }
}
