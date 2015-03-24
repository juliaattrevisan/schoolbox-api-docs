<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

use \Schoolbox\Assessment\Activity;
use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use \Schoolbox\Assessment\Component\OnlineAssessment\Attempt;

class External_Markbook implements External_MarkbookInterface
{

    private $database;

    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct($databaseName = '') {
        $factory = new External_DatabaseFactory();
        $this->setDatabase($factory->buildMarkbookConnector($databaseName));
    }

    /**
     * Returns the assessment information if found in remote system
     *
     * @param Activity $assessment
     * @return array
     */
    public function getAssessment(Activity $assessment)
    {
        return $this->getDatabase()->getAssessment($assessment);
    }

    /**
     * Creates a new assessment item in the markbook
     *
     * @param Activity $assessment
     * @return mixed
     */
    public function createAssessment(Activity $assessment)
    {
        return $this->getDatabase()->createAssessment($assessment);
    }

    /**
     * Updates an existing markbook assessment
     *
     * @param Activity $assessment
     * @return mixed
     */
    public function updateAssessment(Activity $assessment)
    {
        return $this->getDatabase()->updateAssessment($assessment);
    }

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param ReturnedWork|Attempt $returnedWork
     * @return bool
     */
    public function setMark( $returnedWork)
    {
        return $this->getDatabase()->setMark($returnedWork);
    }

    /**
     * @param mixed $database
     */
    public function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return mixed
     */
    public function getDatabase()
    {
        return $this->database;
    }

} 