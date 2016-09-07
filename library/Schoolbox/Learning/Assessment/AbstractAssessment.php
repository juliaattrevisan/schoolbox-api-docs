<?php

/**
 * Assessable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Mapper\Traits\Model\Id;
use \Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use \Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use \Schoolbox\Mapper\Traits\Model\DeletedAtDate;

abstract class AbstractAssessment implements IAssessable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var \Alaress_Date_Format
     */
    protected $publishAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $openAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $dueAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $closeAt;

    /**
     * @var float
     */
    protected $weight;

    /**
     * A Collection of Participant instances
     *
     * @var
     */
    protected $allParticipants;

    /**
     * The person who created this Assessable
     *
     * @var User
     */
    protected $creator;

    /**
     * Assessable constructor.
     */
    public function __construct()
    {
        $this->setAllParticipants(new Collection());
    }

    /**
     * George Cloney
     */
    public function __clone()
    {
        $this->setPublishAt($this->getPublishAt() instanceof \Alaress_Date_Format ? clone $this->getPublishAt() : null);
        $this->setOpenAt($this->getOpenAt() instanceof \Alaress_Date_Format ? clone $this->getOpenAt() : null);
        $this->setDueAt($this->getDueAt() instanceof \Alaress_Date_Format ? clone $this->getDueAt() : null);
        $this->setCloseAt($this->getCloseAt() instanceof \Alaress_Date_Format ? clone $this->getCloseAt() : null);

        /* Explicitly dump away all the Participants
         */
        $this->setAllParticipants(new Collection());

        $this->setCreatedAt($this->getCreatedAt() instanceof \Alaress_Date_Format ? clone $this->getCreatedAt() : null);
        $this->setUpdatedAt($this->getUpdatedAt() instanceof \Alaress_Date_Format ? clone $this->getUpdatedAt() : null);
        $this->setDeletedAt($this->getDeletedAt() instanceof \Alaress_Date_Format ? clone $this->getDeletedAt() : null);
    }

    /* Delegation Methods
     */

    /**
     * Find a reference to a particular Participant in this Assessable
     * which saves you this traversal in Controllers etc
     *
     * @param User $user
     * @return Participant
     */
    public function findParticipant(User $user)
    {
        /** @var Participant $currentParticipant */
        foreach ($this->getAllParticipants() as $currentParticipant) {
            if ($currentParticipant->getUser()->getId() == $user->getId()) {
                return $currentParticipant;
            }
        }

        /* TODO: Throw exception when you can't find a user?
         */
        throw new \OutOfBoundsException();
    }

    /* Traversals
     */

    /**
     * @inheritdoc
     *
     * @return mixed
     */
    public function numParticipants()
    {
        return $this->getAllParticipants()->count();
    }

    /**
     * @inheritdoc
     *
     * @return int
     */
    public function numMarked()
    {
        $num = 0;

        /** @var Participant $currentParticipant */
        foreach ($this->getAllParticipants() as $currentParticipant) {
            if ($currentParticipant->isAssessableMarked($this)) {
                $num++;
            }
        }

        return $num;
    }

    /**
     * @inheritdoc
     *
     * @return int
     */
    public function calculatePercentageMarked()
    {
        return 100 * ($this->numMarked() / $this->numParticipants());
    }

    /**
     * @inheritdoc
     */
    public function numPublished()
    {
        $num = 0;

        /** @var Participant $currentParticipant */
        foreach ($this->getAllParticipants() as $currentParticipant) {
            if ($currentParticipant->isAssessablePublished($this)) {
                $num++;
            }
        }

        return $num;
    }

    /**
     * @inheritdoc
     */
    public function calculatePercentagePublished()
    {
        return 100 * ($this->numPublished() / $this->numParticipants());
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getPublishAt()
    {
        return $this->publishAt;
    }

    /**
     * @param \Alaress_Date_Format $publishAt
     * @return $this
     */
    public function setPublishAt($publishAt)
    {
        $this->publishAt = $publishAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getOpenAt()
    {
        return $this->openAt;
    }

    /**
     * @param \Alaress_Date_Format $openAt
     * @return $this
     */
    public function setOpenAt($openAt)
    {
        $this->openAt = $openAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDueAt()
    {
        return $this->dueAt;
    }

    /**
     * @param \Alaress_Date_Format $dueAt
     * @return $this
     */
    public function setDueAt($dueAt)
    {
        $this->dueAt = $dueAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCloseAt()
    {
        return $this->closeAt;
    }

    /**
     * @param \Alaress_Date_Format $closeAt
     * @return $this
     */
    public function setCloseAt($closeAt)
    {
        $this->closeAt = $closeAt;
        return $this;
    }

    /**
     * @return float
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param float $weight
     * @return $this
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return User
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @param User $creator
     * @return $this
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAllParticipants()
    {
        return $this->allParticipants;
    }

    /**
     * @param mixed $allParticipants
     * @return $this
     */
    public function setAllParticipants($allParticipants)
    {
        $this->allParticipants = $allParticipants;
        return $this;
    }


}

