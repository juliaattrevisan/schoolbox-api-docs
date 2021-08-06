<?php

/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Config\Loader;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\User;

class External_Markbook implements External_MarkbookInterface
{

    /** @var External_MarkbookInterface */
    private $database;


    /**
     * Constructor
     *
     * @throws LogicException If unable to build connection
     */
    public function __construct()
    {
        $this->database = (new External_DatabaseFactory())->buildMarkbookConnector(Loader::getInstance());
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
    public function hasAssessment(IAssessable $assessment): bool
    {
        return $this->database->hasAssessment($assessment);
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


    /**
     * @inheritDoc
     */
    public function deleteFeedback(IAssessable $assessment, User $student): bool
    {
        return $this->database->deleteFeedback($assessment, $student);
    }
}
