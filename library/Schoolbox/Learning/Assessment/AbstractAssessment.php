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

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use \Schoolbox\Collection;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
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
     * @var Collection|Participant[]
     */
    protected $allParticipants;

    /**
     * The person who created this Assessable
     *
     * @var User
     */
    protected $creator;

    /**
     * @var MarkType
     */
    private $markType;

    /**
     * @var GradeGroup|null
     */
    private $gradeGroup;

    /**
     * @var IMarkScheme
     */
    private $markScheme;

    /**
     * @var Collection|IResponse[]
     */
    private $responses;

    /**
     * @var Collection|IFeedback
     */
    private $feedbacks;

    /**
     * Assessable constructor.
     */
    public function __construct()
    {
        $this->setAllParticipants(new Collection());
        $this->responses = new Collection();
        $this->feedbacks = new Collection();
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
     * @throws \UnexpectedValueException
     */
    public function getParticipant(User $user)
    {
        /** @var Participant $currentParticipant */
        foreach ($this->getAllParticipants() as $currentParticipant) {
            if ($currentParticipant->getUser()->getId() == $user->getId()) {
                return $currentParticipant;
            }
        }

        throw new \UnexpectedValueException();
    }

    /**
     * Get the Human Readable Mark value for a particular User
     *
     * Optionally get the Published Human Readable Mark or the
     * currently-unpublished Human Readable Mark
     *
     * @param User $user
     * @param bool $publishedOnly
     * @return string|null
     */
    public function getHumanReadableMark(User $user, $publishedOnly)
    {
        $participant = $this->getParticipant($user);

        $feedback = $publishedOnly
            ? $participant->findMostRecentPublishedFeedback($this)
            : $participant->findMostRecentFeedback($this);

        $response = null === $feedback ? null : $feedback->getResponse();

        return $this->getMarkScheme()->getHumanReadableMark($this->getNormalisedMark($user, $publishedOnly), $response);
    }

    /**
     * Get the Normalised Mark value for a particular User
     *
     * Optionally get the Published Normalised Mark or the
     * currently-unpublished Normalised Mark
     *
     * @param User $user
     * @param bool $publishedOnly
     * @return float|null
     */
    public function getNormalisedMark(User $user, $publishedOnly)
    {
        return $this->getMark($user, $publishedOnly, true);
    }

    /**
     * Get the Mark for a particular User
     *
     * This is a macro method which can extract the normalised/human-readable
     * versions of the Mark. Also accounts for whether the Mark was in a Feedback
     * that was Published, or not.
     *
     * @param User $user
     * @param $publishedOnly
     * @param $normalised
     * @return float|null|string
     */
    private function getMark(User $user, $publishedOnly, $normalised)
    {
        $participant = $this->getParticipant($user);

        /* Get the Mark that was Published
         */
        if ($publishedOnly) {
            $feedback = $participant->findMostRecentPublishedFeedback($this);
        }
        /* Otherwise just use the latest Feedback's current Mark value
         */
        else {
            $feedback = $participant->findMostRecentFeedback($this);
        }

        /* If you're after the Normalised numeric mark
         */
        if ($normalised) {
            if ($feedback) {
                return $feedback->getNormalisedMark();
            }
        }

        if ($feedback) {
            return $feedback->getHumanReadableMark();
        }

        return '';
    }

    /* Traversals
     */

    /**
     * @inheritdoc
     *
     * @return int
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
     */
    public function getPercentageMarked()
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
    public function getPercentagePublished()
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
     * @return Collection|Participant[]
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

    /**
     * @return MarkType
     */
    public function getMarkType()
    {
        return $this->markType;
    }

    /**
     * @param MarkType $markType
     * @return static
     */
    public function setMarkType(MarkType $markType)
    {
        $this->markType = $markType;
        return $this;
    }

    /**
     * @return GradeGroup|null
     */
    public function getGradeGroup()
    {
        return $this->gradeGroup;
    }

    /**
     * Default behaviour is not to permit nulls. Sub-classes can override this (eg Quiz + Task)
     *
     * @param GradeGroup $gradeGroup
     * @return static
     */
    public function setGradeGroup(GradeGroup $gradeGroup)
    {
        $this->gradeGroup = $gradeGroup;
        return $this;
    }

    /**
     * @return IMarkScheme
     */
    public function getMarkScheme()
    {
        return $this->markScheme;
    }

    /**
     * @param IMarkScheme $markScheme
     * @return static
     */
    public function setMarkScheme(IMarkScheme $markScheme)
    {
        $this->markScheme = $markScheme;
        return $this;
    }

    /**
     * @return Collection|IResponse[]
     */
    public function getResponses()
    {
        return $this->responses;
    }

    /**
     * @param Collection|IResponse[] $responses
     * @return $this
     */
    public function setResponses(Collection $responses)
    {
        $this->responses = $responses;
        return $this;
    }

    /**
     * @return Collection|IFeedback[]
     */
    public function getFeedbacks()
    {
        return $this->responses;
    }

    /**
     * @param Collection|IFeedback[] $responses
     * @return $this
     */
    public function setFeedbacks(Collection $responses)
    {
        $this->responses = $responses;
        return $this;
    }
}

