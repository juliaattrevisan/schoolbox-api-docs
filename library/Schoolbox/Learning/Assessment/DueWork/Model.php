<?php
/**
 * DueWork
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Schoolbox\Assessment\Rubric\Rubric;
use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;
use Schoolbox\Error\Mapper as ErrorMapper;

class Model extends AbstractAssessment
{
    /**
     * Accept zero submissions online
     */
    const ACCEPT_ONLINE_NONE = 0;

    /**
     * Accept one submission online
     */
    const ACCEPT_ONLINE_SINGLE = 1;

    /**
     * Accept multiple submissions online
     */
    const ACCEPT_ONLINE_MULTI = 2;

    /**
     * All the Files that are part of the Due Work specification
     * e.g. "Here are some pictures to look at, so you can complete
     * this Due Work"
     *
     * @var Collection|File[]
     */
    protected $attachedFiles;

    /**
     * The "type" of the Due Work
     * (Essay, Homework, etc)
     *
     * @var DueWorkType
     */
    protected $type;

    /**
     * An enum for accepting submissions online
     *
     * @var int
     */
    protected $acceptOnline;

    /**
     * Show students who haven't submitted to this Due Work
     *
     * @var bool
     */
    protected $showSubmittedOnly = false;

    /**
     * This DueWork has an associated Rubric
     *
     * @var bool
     * @todo Is this now superfluous?
     * @todo Should this be determined dynamically by whether this DueWork has an attached Rubric?
     */
    protected $hasRubric = false;

    /**
     * @var Rubric|null
     */
    private $rubric;

    /**
     * This DueWork has Mark Per Capability on
     *
     * @var bool
     */
    protected $hasMarkPerCapability = false;

    /**
     * Only relevant if this DueWork's MarkType is 'TotalMark'
     *
     * @var $int
     */
    private $maxMark;

    /**
     * Model constructor.
     */
    public function __construct()
    {

        /* I know Dr hates hook methods, so do this instead
         */
        parent::__construct();

        $this->setAttachedFiles(new Collection());
        $this->setType(new DueWorkType());
        $this->setAcceptOnline(self::ACCEPT_ONLINE_NONE);
        $this->setAttachedFiles(new Collection());
    }

    /**
     * Work out if we are going to accept responses
     *
     * It's not accepting submissions if:
     *      - If it's not open
     *      - If the close date has passed
     *      - If the assessment is set to Accept None
     *      - If the assessment only accepts single, and a response already exists for this participant
     *      - You're not a student of this assessment's class (folder)
     *
     * @throws \DomainException
     */
    public function isAcceptSubmission()
    {
        if ($this->getOpenAt()->gt(\Alaress_Date_Format::now())) {
            throw new \DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NOT_YET_OPEN);
        }

        if (\Alaress_Date_Format::now()->gt($this->getCloseAt())) {
            throw new \DomainException(null, ErrorMapper::ERROR_ASSESSMENT_CLOSED);
        }

        if ($this->isAcceptNone()) {
            throw new \DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NO_ONLINE_RESPONSE);
        }

        if ($this->isAcceptSingle() && $this->getResponses()->count()) {
            throw new \DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NO_MULTIPLE_RESPONSE);
        }
    }

    /**
     * @inheritdoc
     */
    public function getFamilyName()
    {
        return 'dueWork';
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getTypeName()
    {
        $subTypeName = $this->type->getName();
        return $subTypeName ?: 'Due Work';
    }

    /**
     * DueWork does not use the MarkScheme. It merely returns the cached value.
     *
     * @param User $user
     * @param bool $publishedOnly
     * @return string|null
     */
    public function getHumanReadableMark(User $user, $publishedOnly)
    {
        $normalised = false;
        return $this->getMark($user, $publishedOnly, $normalised);
    }

    /**
     * DueWork does not use the MarkScheme. It merely returns the cached value.
     *
     * @param User $user
     * @param bool $publishedOnly
     * @return float|null
     */
    public function getNormalisedMark(User $user, $publishedOnly)
    {
        $normalised = true;
        return $this->getMark($user, $publishedOnly, $normalised);
    }

    /* Getters/Setters
     */

    /**
     * This DueWork accepts no submissions online
     *
     * @return $this
     */
    public function setAcceptsNone()
    {
        $this->setAcceptOnline(self::ACCEPT_ONLINE_NONE);
        return $this;
    }

    /**
     * Does this DueWork accept no submission online?
     * @return bool
     */
    public function isAcceptNone()
    {
        return $this->getAcceptOnline() == self::ACCEPT_ONLINE_NONE;
    }

    /**
     * This DueWork accepts one submissions online
     *
     * @return $this
     */
    public function setAcceptsSingle()
    {
        $this->setAcceptOnline(self::ACCEPT_ONLINE_SINGLE);
        return $this;
    }

    /**
     * Does this DueWork accept one submission online?
     *
     * @return bool
     */
    public function isAcceptSingle()
    {
        return $this->getAcceptOnline() == self::ACCEPT_ONLINE_SINGLE;
    }

    /**
     * This DueWork accepts multiple submissions online
     *
     * @return $this
     */
    public function setAcceptsMultiple()
    {
        $this->setAcceptOnline(self::ACCEPT_ONLINE_MULTI);
        return $this;
    }

    /**
     * Does this DueWork accept multiple submissions online?
     * @return bool
     */
    public function isAcceptMulti()
    {
        return $this->getAcceptOnline() == self::ACCEPT_ONLINE_MULTI;
    }

    /**
     * Only show Students who have submitted to this DueWork
     *
     * @return $this
     */
    public function showOnlySubmittedStudents()
    {
        $this->setShowSubmittedOnly(true);
        return $this;
    }

    /**
     * Show all Students who can submit to this DueWork
     *
     * @return $this
     */
    public function showAllSubmissibleStudents()
    {
        $this->setShowSubmittedOnly(false);
        return $this;
    }

    /**
     * This DueWork has an associated Rubric
     */
    public function hasRubric()
    {
        return $this->getHasRubric();
    }

    /**
     * Use rubrics
     *
     * @return $this
     */
    public function useRubric()
    {
        $this->setHasRubric(true);
        return $this;
    }

    /**
     * Don't use rubrics
     *
     * @return $this
     */
    public function noRubric()
    {
        $this->setHasRubric(false);
        return $this;
    }

    /**
     * This DueWork has Mark Per Capability on
     *
     * @return bool
     */
    public function hasMarkPerCapbility()
    {
        return $this->getHasMarkPerCapability();
    }

    /**
     * This DueWork's Rubrics uses Mark Per Capability
     *
     * @return $this
     */
    public function useMarkPerCapability()
    {
        $this->setHasMarkPerCapability(true);
        return $this;
    }

    /**
     * This DueWork's Rubrics does not use Mark Per Capability
     *
     * @return $this
     */
    public function noMarkPerCapability()
    {
        $this->setHasMarkPerCapability(false);
        return $this;
    }

    /**
     * @return null|Rubric
     */
    public function getRubric()
    {
        return $this->rubric;
    }

    /**
     * @param Rubric|null $rubric
     * @return static
     */
    public function setRubric(Rubric $rubric = null)
    {
        $this->rubric = $rubric;
        return $this;
    }

    /**
     * @return DueWorkType
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param DueWorkType $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return int
     */
    public function getAcceptOnline()
    {
        return $this->acceptOnline;
    }

    /**
     * @param int $acceptOnline
     * @return $this
     */
    public function setAcceptOnline($acceptOnline)
    {
        $this->acceptOnline = $acceptOnline;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getShowSubmittedOnly()
    {
        return $this->showSubmittedOnly;
    }

    /**
     * @param boolean $showSubmittedOnly
     * @return $this
     */
    public function setShowSubmittedOnly($showSubmittedOnly)
    {
        $this->showSubmittedOnly = $showSubmittedOnly;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getHasRubric()
    {
        return $this->hasRubric;
    }

    /**
     * @param boolean $hasRubric
     * @return $this
     */
    protected function setHasRubric($hasRubric)
    {
        $this->hasRubric = $hasRubric;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxMark()
    {
        return $this->maxMark;
    }

    /**
     * @param int $maxMark
     * @return static
     */
    public function setMaxMark($maxMark)
    {
        $this->maxMark = $maxMark;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getHasMarkPerCapability()
    {
        return $this->hasMarkPerCapability;
    }

    /**
     * @param boolean $hasMarkPerCapability
     * @return $this
     */
    public function setHasMarkPerCapability($hasMarkPerCapability)
    {
        $this->hasMarkPerCapability = $hasMarkPerCapability;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getAttachedFiles()
    {
        return $this->attachedFiles;
    }

    /**
     * @param Collection $attachedFiles
     * @return $this
     */
    public function setAttachedFiles($attachedFiles)
    {
        $this->attachedFiles = $attachedFiles;
        return $this;
    }
}