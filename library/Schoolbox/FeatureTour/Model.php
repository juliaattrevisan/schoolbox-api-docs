<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\FeatureTour;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model implements ISoftDeletable
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /** @var string */
    private $uuid;

    /** @var string */
    private $title;

    /** @var string */
    private $description;

    /** @var bool */
    private $active;


    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }


    /**
     * @param string $uuid
     * @return $this
     */
    public function setUuid($uuid): self
    {
        $this->uuid = $uuid;
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
     * @return $this
     */
    public function setTitle($title): self
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }


    /**
     * @param string $description
     * @return $this
     */
    public function setDescription($description): self
    {
        $this->description = $description;
        return $this;
    }


    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }


    /**
     * @deprecated for Mapper-use only, use isActive()
     * @return bool
     */
    public function getActive(): bool
    {
        return $this->isActive();
    }


    /**
     * @param bool $active
     * @return $this
     */
    public function setActive($active): self
    {
        $this->active = $active;
        return $this;
    }
}
