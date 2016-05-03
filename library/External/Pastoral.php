<?php
/**
 *
 * @author      Damian Sloane <jdsloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

use \Schoolbox\Pastoral\Pastoral\Model as PastoralModel;
use \Schoolbox\Pastoral\Comment\Model as PastoralCommentModel;

class External_Pastoral implements External_PastoralInterface
{
    /**
     * @var External_PastoralInterface
     */
    private $database;

    /**
     * @var External_Offline_Pastoral
     */
    private $offline;
    
    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct($databaseName = '') {
        $factory = new External_DatabaseFactory();
        $this->setDatabase($factory->buildPastoralConnector($databaseName));
        $this->setOffline(new External_Offline_Pastoral());
    }

    /**
     * Returns the pastoral record if found in remote system
     *
     * @param PastoralModel $pastoral
     * @return array
     */
    public function getPastoral(PastoralModel $pastoral)
    {
        return $this->getDatabase()->getPastoral($pastoral);
    }

    /**
     * Import pastoral care records from external SIS
     */
    public function importPastoral()
    {
        return $this->getDatabase()->importPastoral();
    }

    /**
     * Creates a new pastoral record in the remote system
     *
     * @param PastoralModel $pastoral
     * @return mixed
     */
    public function createPastoral(PastoralModel $pastoral)
    {
        return $this->getDatabase()->createPastoral($pastoral);
    }

    /**
     * Updates an existing pastoral assessment
     *
     * @param PastoralModel $pastoral
     * @return mixed
     */
    public function updatePastoral(PastoralModel $pastoral)
    {
        return $this->getDatabase()->updatePastoral($pastoral);
    }

    /**
     * Creates a new pastoral comment in the remote system
     *
     * @param PastoralCommentModel $comment
     * @return mixed
     */
    public function createComment(PastoralCommentModel $comment)
    {
        return $this->getDatabase()->createComment($comment);
    }

    /**
     * Updates an existing pastoral comment
     *
     * @param PastoralCommentModel $comment
     * @return mixed
     */
    public function updateComment(PastoralCommentModel $comment)
    {
        return $this->getDatabase()->updateComment($comment);
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(PastoralModel $pastoral)
    {
        return $this->getDatabase()->deletePastoral($pastoral);
    }

    /**
     * @inheritDoc
     */
    public function deleteComment(PastoralCommentModel $comment)
    {
        return $this->getDatabase()->deleteComment($comment);
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

    /**
     * @return External_Offline_Pastoral
     */
    public function getOffline()
    {
        return $this->offline;
    }

    /**
     * @param External_Offline_Pastoral $offline
     */
    public function setOffline($offline)
    {
        $this->offline = $offline;
    }

} 