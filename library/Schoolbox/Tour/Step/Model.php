<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Tour\Step;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\Uuid;
use Schoolbox\Tour\Model as Tour;
use Schoolbox\User;
use Schoolbox\Tour\Step\Type\AbstractModel;

class Model implements ISoftDeletable, \JsonSerializable
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
     * @var AbstractModel
     */
    protected $type;

    /**
     * @var Model|null
     */
    protected $nextStep;

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
     * @return AbstractModel
     */
    public function getType(): AbstractModel
    {
        return $this->type;
    }

    /**
     * @param AbstractModel $type
     *
     * @return self
     */
    public function setType(AbstractModel $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return Model|null
     */
    public function getNextStep(): ?Model
    {
        return $this->nextStep;
    }

    /**
     * @param Model|null $nextStep
     *
     * @return self|$this
     */
    public function setNextStep(?Model $nextStep): self
    {
        $this->nextStep = $nextStep;
        return $this;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @link  http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $json = $this->getType()->jsonSerialize();
        $json['uuid'] = $this->getUuid();
        if ($this->getNextStep()) {
            $json['next_step_uuid'] = $this->getNextStep()->getUuid();
        } else {
            $json['next_step_uuid'] = null;
        }

        return $json;
    }
}
