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

use DomainException;
use InvalidArgumentException;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Rubric;
use \Schoolbox\Collection;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;

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
     * @var WorkType
     */
    protected $workType;

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
     * This DueWork uses a Rubric
     *
     * This property IS required. We cannot just inspect the attached Rubric because in some
     * scenarios (eg Collections) we don't hydrate the Rubric.
     *
     * @var bool
     */
    protected $useRubric = false;

    /**
     * @var Rubric|null
     */
    private $rubric;

    /**
     * This DueWork's Rubric has Mark Per Capability on
     *
     * @var bool
     */
    protected $markPerCapability = false;

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
        $this->setWorkType(new WorkType());
        $this->setAcceptOnline(self::ACCEPT_ONLINE_NONE);
        $this->setAttachedFiles(new Collection());
    }


    /**
     * The friendly, uninformative version of assertAcceptSubmissions.
     *
     * @param Collection $responses
     * @return bool
     */
    public function isAcceptingSubmissions(\Schoolbox\Collection $responses)
    {
        try {
            $this->assertAcceptSubmission($responses);
            return true;
        } catch (DomainException $e) {
            return false;
        }
    }

    /**
     * Work out if we are going to accept responses
     *
     * It's not accepting submissions if:
     *      - If it's not open
     *      - If the close date has passed
     *      - If the assessment is set to Accept None
     *      - If the assessment only accepts single, and a submitted response already exists for this participant
     *      - You're not a student of this assessment's class (folder)
     *
     * @param Collection $responses
     * @throws DomainException
     */
    public function assertAcceptSubmission(\Schoolbox\Collection $responses)
    {
        if ($this->getOpenAt()->gt(\Alaress_Date_Format::now())) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NOT_YET_OPEN);
        }

        if (\Alaress_Date_Format::now()->gt($this->getCloseAt())) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_CLOSED);
        }

        if ($this->isAcceptNone()) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NO_ONLINE_RESPONSE);
        }

        /* Allow draft responses in single submission assessments
         */
        if ($this->isAcceptSingle() && $responses->count() && $responses->first()->isSubmitted()) {
            throw new DomainException(null, ErrorMapper::ERROR_ASSESSMENT_NO_MULTIPLE_RESPONSE);
        }
    }


    /**
     * Check the dates set on this model are valid
     * @throws InvalidArgumentException if the dates are not validly set
     * @return void
     */
    public function assertValidDates()
    {
        /* Check publish date against everything else
        /* Don't check dates if in course */
        if ($this->getBaseContext() instanceof UnitSpecification) {
            return;
        }

        /* Only check publish date */
        if ($this->getAcceptOnline() == self::ACCEPT_ONLINE_NONE) {
            $this->assertDateSequence([
                'publish' => $this->getPublishAt(),
                'due'     => $this->getDueAt()
            ]);

        } else {
            $this->assertDateSequence([
                'publish' => $this->getPublishAt(),
                'open'    => $this->getOpenAt(),
                'due'     => $this->getDueAt(),
                'late'    => $this->getCloseAt()
            ]);
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
        $subTypeName = $this->getWorkType()->getName();
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
     * @return boolean
     */
    public function getUseRubric()
    {
        return $this->useRubric;
    }

    /**
     * @param boolean $useRubric
     * @return static
     */
    public function setUseRubric($useRubric)
    {
        $this->useRubric = $useRubric;
        return $this;
    }

    /**
     * @return Rubric|null
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
     * @return WorkType
     */
    public function getWorkType()
    {
        return $this->workType;
    }

    /**
     * @param WorkType $workType
     * @return self
     */
    public function setWorkType($workType)
    {
        $this->workType = $workType;
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
    public function getMarkPerCapability()
    {
        return $this->markPerCapability;
    }

    /**
     * @param boolean $markPerCapability
     * @return $this
     */
    public function setMarkPerCapability($markPerCapability)
    {
        $this->markPerCapability = $markPerCapability;
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
