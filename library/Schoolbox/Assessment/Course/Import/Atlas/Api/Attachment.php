<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

/**
 * Object representation of an attachment from Atlas Rubicon.
 */
class Attachment
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $url;

    /**
     * @var int
     */
    private $sortOrder;

    /**
     * @var string
     */
    private $attachmentType;

    /**
     * @var string
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
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
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
    public function setUrl(string $url)
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
     * @return string
     */
    public function getAttachmentType(): string
    {
        return $this->attachmentType;
    }

    /**
     * @param string $attachmentType
     * @return static
     */
    public function setAttachmentType(string $attachmentType)
    {
        $this->attachmentType = $attachmentType;
        return $this;
    }

    /**
     * @return string
     */
    public function getItemType(): string
    {
        return $this->itemType;
    }

    /**
     * @param string $itemType
     * @return static
     */
    public function setItemType(string $itemType)
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
}
