<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Package;

use Schoolbox\IModel;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Represents an installable package via the admin system
 * @package Schoolbox\Admin\Package
 */
class Model implements IModel
{
    use Id;
    use CreateCollection;

    /**
     * @var string|null
     */
    protected ?string $title;


    /**
     * @var string|null
     */
    protected ?string $description;

    /**
     * @var string|null
     */
    protected ?string $location;

    /**
     * @var bool|null
     */
    protected ?bool $installed;

    /** @var bool */
    private bool $hidden;

    /**
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string|null $title
     *
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
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     *
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * @param string|null $location
     *
     * @return self
     */
    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }


    /**
     * @return bool|null
     */
    public function isInstalled(): ?bool
    {
        return $this->getInstalled();
    }

    /**
     * @return bool|null
     */
    public function getInstalled(): ?bool
    {
        return $this->installed;
    }

    /**
     * @param bool|null $installed
     *
     * @return self|$this
     */
    public function setInstalled(?bool $installed): self
    {
        $this->installed = $installed;

        return $this;
    }

    /**
     * @return bool
     */
    public function getHidden(): ?bool
    {
        return $this->hidden;
    }

    /**
     * @return bool
     */
    public function isHidden(): ?bool
    {
        return $this->getHidden();
    }


    /**
     * @param bool $hidden
     * @return $this
     */
    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * @return string
     */
    public function getHiddenString(): string
    {
        return $this->hidden ? 'Hidden' : 'Not Hidden';
    }

    /**
     * @return string
     */
    public function getInstalledString(): string
    {
        return $this->installed ? 'Installed' : 'Not Installed';
    }
}
