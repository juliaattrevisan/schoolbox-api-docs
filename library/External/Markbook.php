<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;

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
    public function isSyncable(IAssessable $assessment): bool
    {
        return $this->database->isSyncable($assessment);
    }


    /**
     * @inheritdoc
     */
    public function syncAssessment(IAssessable $assessment): bool
    {
        return $this->database->syncAssessment($assessment);
    }


    /**
     * @inheritdoc
     */
    public function getAssessment(IAssessable $assessment): array
    {
        return $this->database->getAssessment($assessment);
    }


    /**
     * @inheritdoc
     */
    public function hasAssessment(IAssessable $assessment): bool
    {
        return $this->database->hasAssessment($assessment);
    }


    /**
     * @inheritdoc
     */
    public function createAssessment(IAssessable $assessment): bool
    {
        return $this->database->createAssessment($assessment);
    }


    /**
     * @inheritdoc
     */
    public function updateAssessment(IAssessable $assessment): bool
    {
        return $this->database->updateAssessment($assessment);
    }


    /**
     * @inheritDoc
     */
    public function deleteAssessment(IAssessable $assessment): bool
    {
        return $this->database->deleteAssessment($assessment);
    }


    /**
     * @param IFeedback $returnedWork
     * @return bool
     *
     * @inheritdoc
     */
    public function syncFeedback(IFeedback $returnedWork): bool
    {
        return $this->database->syncFeedback($returnedWork);
    }
}
