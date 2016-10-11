<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Quiz\Attempt;

use \Schoolbox\Learning\Assessment\AbstractResponse;

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
     * Attempts are submitted at the updatedAt value
     * in case the Attempt is modified by the Student
     *
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->getUpdatedAt();
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