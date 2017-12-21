<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Tour;

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
    protected $description;

    /**
     * @var bool|null
     */
    protected $active;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var TourStep|null
     */
    protected $tourStep;

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
    public function isActive(): ?bool
    {
        return $this->getActive();
    }

    /**
     * @return bool|null
     */
    public function getActive(): ?bool
    {
        return $this->active;
    }

    /**
     * @param bool|null $active
     *
     * @return self|$this
     */
    public function setActive(?bool $active): self
    {
        $this->active = $active;

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

}
