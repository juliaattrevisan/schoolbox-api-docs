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

use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;

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
    protected $allFiles;

    /**
     * The "type" of the Due Work
     * (Essay, Homework, etc)
     *
     * @var Type
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
     */
    protected $hasRubric = false;

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

        $this->setAllFiles(new Collection());
        $this->setType(new Type());
        $this->setAcceptOnline(self::ACCEPT_ONLINE_NONE);
    }

    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getTypeName()
    {
        return $this->getType()->getName();
    }

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

    /* Getters/Setters
     */

    /**
     * @return Collection|File[]
     */
    public function getAllFiles()
    {
        return $this->allFiles;
    }

    /**
     * @param Collection|File[] $allFiles
     * @return self
     */
    public function setAllFiles($allFiles)
    {
        $this->allFiles = $allFiles;
        return $this;
    }

    /**
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param Type $type
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
}