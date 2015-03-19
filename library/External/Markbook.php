<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

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
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function getAssessment(\Schoolbox\Assessment\Component\SubmissionBox $assessment)
    {
        return $this->getDatabase()->getAssessment($assessment);
    }

    /**
     * Creates a new assessment item in the markbook
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return mixed
     */
    public function createAssessment(\Schoolbox\Assessment\Component\SubmissionBox $assessment)
    {
        return $this->getDatabase()->createAssessment($assessment);
    }

    /**
     * Updates an existing markbook assessment
     *
     * @param string
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return mixed
     */
    public function updateAssessment($assessmentRemoteID, \Schoolbox\Assessment\Component\SubmissionBox $assessment)
    {
        return $this->getDatabase()->createAssessment($assessmentRemoteID, $assessment);
    }

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param string
     * @param \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork
     * @return bool
     */
    public function setMark($assessmentRemoteID, \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork)
    {
        return $this->getDatabase()->setMark($assessmentRemoteID, $returnedWork);
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