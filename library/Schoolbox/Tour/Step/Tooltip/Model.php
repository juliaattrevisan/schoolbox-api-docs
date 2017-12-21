<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour\Step\Tooltip;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\Uuid;
use Schoolbox\Tour\Step\Model as TourStep;
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
     * @var string|null
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $javascriptSelector;

    /**
     * @var string|null
     */
    protected $cssClass;

    /**
     * @var TourStep|null
     */
    protected $tourStep;

    /**
     * @var string|null
     */
    protected $body;

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
    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    /**
     * @param string|null $cssClass
     *
     * @return self
     */
    public function setCssClass(?string $cssClass): self
    {
        $this->cssClass = $cssClass;
        return $this;
    }

    /**
     * @return TourStep|null
     */
    public function getTourStep(): ?TourStep
    {
        return $this->tourStep;
    }

    /**
     * @param TourStep|null $tourStep
     *
     * @return self|$this
     */
    public function setTourStep(?TourStep $tourStep): self
    {
        $this->tourStep = $tourStep;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * @param string|null $body
     *
     * @return self
     */
    public function setBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }
}