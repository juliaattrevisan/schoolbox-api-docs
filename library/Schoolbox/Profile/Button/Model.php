<?php

declare(strict_types=1);

namespace Schoolbox\Profile\Button;

use Schoolbox\ISoftDeletable;
use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;

class Model implements ISoftDeletable
{
    /**
     * This const conclusively identifies a Custom Button
     * Everything else is deemed to be a System Button
     */
    public const BUTTON_CUSTOM = 'custom';


    use DeletedAtDate;

    /** @var int */
    protected $id;

    /** @var int */
    protected $sortOrder;

    /** @var string|null */
    protected $title;

    /** @var string */
    protected $text;

    /** @var string|null */
    protected $icon;

    /** @var string */
    protected $url;

    /** @var string */
    protected $css;

    /** @var string|null */
    protected $target;


    /**
     * A string that helps identify truly Custom buttons made by schools vs the
     * special system buttons that we have by default. It'll be "custom" for the
     * ones created by schools; ours will have a unique string that conclusively
     * identifies a system button even when the school totally pollutes the data
     *
     * @var string|null
     */
    protected ?string $type = null;

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
     * @param string|null $title
     * @return self
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;
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
     * @param string|null $icon
     * @return self
     */
    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getIcon(): ?string
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
     * @return string|null
     */
    public function getCss(): ?string
    {
        return $this->css;
    }


    /**
     * @param string|null $css
     * @return self
     */
    public function setCss(?string $css): self
    {
        $this->css = $css;
        return $this;
    }

    /**
     * @param string|null $target
     * @return self
     */
    public function setTarget(?string $target): self
    {
        $this->target = $target;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTarget(): ?string
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
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }


    /**
     * @param string|null $type
     * @return $this
     */
    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }
}
