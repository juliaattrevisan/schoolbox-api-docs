<?php
/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Package;

use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Represents an installable package via the admin system
 * @package Schoolbox\Admin\Package
 */
class Model
{
    use Id;

    /**
     * @var string|null
     */
    protected ?string $title;


    /**
     * @var string|null
     */
    protected ?string $description;

    /**
     * @var bool|null
     */
    protected ?bool $installed;

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
}
