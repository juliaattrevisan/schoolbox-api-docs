<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Trigger;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\Uuid;
use Schoolbox\Tour\Model as Tour;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use Uuid;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var User|null
     */
    protected $author;

    /**
     * @var Tour|null
     */
    protected $tour;

    /**
     * @var string|null
     */
    protected $javascriptSelector;

    /**
     * @var string|null
     */
    protected $javascriptEvent;

    /**
     * @var string|null
     */
    protected $urlRegex;

    /**
     * @return User|null
     */
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    /**
     * @param User|null $author
     *
     * @return self|$this
     */
    public function setAuthor(?User $author): self
    {
        $this->author = $author;
        return $this;
    }

    /**
     * @return Tour|null
     */
    public function getTour(): ?Tour
    {
        return $this->tour;
    }

    /**
     * @param Tour|null $tour
     *
     * @return self|$this
     */
    public function setTour(?Tour $tour): self
    {
        $this->tour = $tour;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getJavascriptSelector(): ?string
    {
        return $this->javascriptSelector;
    }

    /**
     * @param string|null $javascriptSelector
     *
     * @return self
     */
    public function setJavascriptSelector(?string $javascriptSelector): self
    {
        $this->javascriptSelector = $javascriptSelector;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getJavascriptEvent(): ?string
    {
        return $this->javascriptEvent;
    }

    /**
     * @param string|null $javascriptEvent
     *
     * @return self
     */
    public function setJavascriptEvent(?string $javascriptEvent): self
    {
        $this->javascriptEvent = $javascriptEvent;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUrlRegex(): ?string
    {
        return $this->urlRegex;
    }

    /**
     * @param string|null $urlRegex
     *
     * @return self
     */
    public function setUrlRegex(?string $urlRegex): self
    {
        $this->urlRegex = $urlRegex;
        return $this;
    }
}
