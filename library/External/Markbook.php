<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;

class External_Markbook implements External_MarkbookInterface
{

    /** @var External_MarkbookInterface */
    private $database;


    /**
     * Constructor
     *
     * @param String $databaseName
     * @throws LogicException If unable to build connection
     */
    public function __construct(string $databaseName = '')
    {
        $this->database = (new External_DatabaseFactory())->buildMarkbookConnector($databaseName);
    }


    /**
     * @inheritdoc
     */
    public function getAssessment(Activity $assessment): array
    {
        return $this->database->getAssessment($assessment);
    }


    /**
     * @inheritdoc
     */
    public function hasAssessment(Activity $assessment): bool
    {
        return $this->database->hasAssessment($assessment);
    }


    /**
     * @inheritdoc
     */
    public function createAssessment(Activity $assessment): bool
    {
        return $this->database->createAssessment($assessment);
    }


    /**
     * @inheritdoc
     */
    public function updateAssessment(Activity $assessment): bool
    {
        return $this->database->updateAssessment($assessment);
    }


    /**
     * @inheritDoc
     */
    public function deleteAssessment(Activity $assessment): bool
    {
        return $this->database->deleteAssessment($assessment);
    }


    /**
     * @param ReturnedWork|QuizAttempt $returnedWork
     * @return bool
     *
     * @inheritdoc
     */
    public function setMark($returnedWork): bool
    {
        return $this->database->setMark($returnedWork);
    }
}
