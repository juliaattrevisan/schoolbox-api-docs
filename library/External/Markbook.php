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
    public function __construct($databaseName = '')
    {
        $factory = new External_DatabaseFactory();
        $this->setDatabase($factory->buildMarkbookConnector($databaseName));
        $this->setOffline(new External_Offline_Markbook());
    }

    /**
     * @inheritdoc
     */
    public function getAssessment(Activity $assessment)
    {
        return $this->getDatabase()->getAssessment($assessment);
    }

    /**
     * @inheritdoc
     */
    public function hasAssessment(Activity $assessment)
    {
        return $this->getDatabase()->hasAssessment($assessment);
    }

    /**
     * @inheritdoc
     */
    public function createAssessment(Activity $assessment)
    {
        return $this->getDatabase()->createAssessment($assessment);
    }

    /**
     * @inheritdoc
     */
    public function updateAssessment(Activity $assessment)
    {
        return $this->getDatabase()->updateAssessment($assessment);
    }

    /**
     * @inheritDoc
     */
    public function deleteAssessment(Activity $assessment)
    {
        return $this->getDatabase()->deleteAssessment($assessment);
    }

    /**
     * @inheritdoc
     */
    public function setMark($returnedWork)
    {
        return $this->getDatabase()->setMark($returnedWork);
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
     * @return self
     */
    public function setOffline($offline)
    {
        $this->offline = $offline;
        return $this;
    }
}
