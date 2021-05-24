<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Attempt;

use Alaress\Date\Interval;
use Schoolbox\Learning\Assessment\AbstractResponse;

class Model extends AbstractResponse
{
    /**
     * Total marks awarded in this attempt
     * @var float
     */
    private $marksGiven;

    /**
     * Total marks available in this attempt
     *
     * @var float
     */
    private $marksAvailable;

    /**
     * The first section in this attempt.
     *
     * Required so we can generate a link to the /assess/review/:quidId/:sectionId/:attemptId screen
     *
     * @var int
     */
    private $firstSectionId;

    /**
     * When was this submitted?
     *
     * TODO this should accept NULL values instead of Alaress_Date_NullDate
     *
     * @var \Alaress_Date_Format
     */
    protected $submittedAt;


    /**
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->submittedAt;
    }

    /**
     * @param \Alaress_Date_Format $submittedAt
     * @return $this
     */
    public function setSubmittedAt(\Alaress_Date_Format $submittedAt)
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }


    /**
     * @return Interval
     */
    public function getDuration()
    {
        if (!$this->isSubmitted()) {
            return null;
        }
        return new Interval(
            $this->getSubmittedAt()->diffInSeconds($this->getCreatedAt())
        );
    }

    /**
     * @return float
     */
    public function getMarksGiven()
    {
        return $this->marksGiven;
    }

    /**
     * @param float $marksGiven
     * @return static
     */
    public function setMarksGiven($marksGiven)
    {
        $this->marksGiven = $marksGiven;
        return $this;
    }

    /**
     * @return float
     */
    public function getMarksAvailable()
    {
        return $this->marksAvailable;
    }

    /**
     * @param float $marksAvailable
     * @return static
     */
    public function setMarksAvailable($marksAvailable)
    {
        $this->marksAvailable = $marksAvailable;
        return $this;
    }

    /**
     * @return int
     */
    public function getFirstSectionId()
    {
        return $this->firstSectionId;
    }

    /**
     * @param int $firstSectionId
     * @return static
     */
    public function setFirstSectionId($firstSectionId)
    {
        $this->firstSectionId = $firstSectionId;
        return $this;
    }
}
