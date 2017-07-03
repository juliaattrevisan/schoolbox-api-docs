<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Activity;

class External_Markbook implements External_MarkbookInterface
{
    /** @var External_MarkbookInterface */
    private $database;

    /** @var External_Offline_Markbook */
    private $offline;

    /**
     * Constructor
     *
     * @param String $databaseName
     */
    public function __construct($databaseName = '') {
        $factory = new External_DatabaseFactory();
        $this->setDatabase($factory->buildMarkbookConnector($databaseName));
        $this->setOffline(new External_Offline_Markbook());
    }

    /**
     * @inheritdoc
     */
    public function getAssessment(Activity $assessment, $year = null, $semester = null)
    {
        return $this->getDatabase()->getAssessment($assessment, $year, $semester);
    }

    /**
     * @inheritdoc
     */
    public function createOrUpdateAssessment(Activity $assessment, $year = null, $semester = null)
    {
        return $this->getDatabase()->createOrUpdateAssessment($assessment, $year, $semester);
    }

    /**
     * @inheritdoc
     */
    public function createAssessment(Activity $assessment, $year = null, $semester = null)
    {
        return $this->getDatabase()->createAssessment($assessment, $year, $semester);
    }

    /**
     * @inheritdoc
     */
    public function updateAssessment(Activity $assessment, $year = null, $semester = null)
    {
        return $this->getDatabase()->updateAssessment($assessment, $year, $semester);
    }

    /**
     * @inheritDoc
     */
    public function deleteAssessment(Activity $assessment, $year = null, $semester = null)
    {
        return $this->getDatabase()->deleteAssessment($assessment, $year, $semester);
    }

    /**
     * @inheritdoc
     */
    public function setMark($returnedWork, $year = null, $semester = null)
    {
        return $this->getDatabase()->setMark($returnedWork, $year, $semester);
    }

    /**
     * @param External_MarkbookInterface $database
     */
    public function setDatabase(External_MarkbookInterface $database)
    {
        $this->database = $database;
    }

    /**
     * @return External_MarkbookInterface
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return External_Offline_Markbook
     */
    public function getOffline()
    {
        return $this->offline;
    }

    /**
     * @param External_Offline_Markbook $offline
     */
    public function setOffline(External_Offline_Markbook $offline)
    {
        $this->offline = $offline;
    }
}