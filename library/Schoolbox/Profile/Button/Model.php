<?php
declare(strict_types=1);

namespace Schoolbox\Profile\Button;

use Schoolbox\ISoftDeletable;
use Alaress_Date_Format;

class Model implements ISoftDeletable
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $sortOrder;

    /** @var string */
    protected $title;

    /** @var string */
    protected $text;

    /** @var string */
    protected $token;

    /** @var string */
    protected $icon;

    /** @var string */
    protected $url;

    /** @var string */
    protected $target;

    /** @var \Alaress_Date_Format */
    protected $deletedAt;

    /**
     * @param int $id
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return null|int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param string $title
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
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
     * @param string $text
     * @return self
     */
    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @param string $token
     * @return self
     */
    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $icon
     * @return self
     */
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;
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
     * @param string $url
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
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
     * @param string $target
     * @return self
     */
    public function setTarget(string $target): self
    {
        $this->target = $target;
        return $this;
    }

    /**
     * @return string
     */
    public function getTarget(): string
    {
        return $this->target;
    }

    /**
     * @param int $sortOrder
     * @return self
     */
    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
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
     * @param Alaress_Date_Format $deletedAt
     * @return self
     */
    public function setDeletedAt(Alaress_Date_Format $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    /**
     * @return null|Alaress_Date_Format $deletedAt
     */
    public function getDeletedAt(): ?Alaress_Date_Format
    {
        return $this->deletedAt;
    }

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return !$this->getDeletedAt() instanceof \Alaress_Date_NullDate;
    }
}