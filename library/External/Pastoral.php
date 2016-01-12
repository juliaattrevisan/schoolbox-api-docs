<?php
/**
 *
 * @author      Damian Sloane <jdsloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

use \Schoolbox\Pastoral\Pastoral;

class External_Pastoral implements External_PastoralInterface
{
    /**
     * @var External_PastoralInterface
     */
    private $database;

    /**
     * Constructor
     *
     * @throws Exception
     * @param String $databaseName
     */
    public function __construct($databaseName = '') {
        $factory = new External_DatabaseFactory();
        $this->setDatabase($factory->buildPastoralConnector($databaseName));
    }

    /**
     * Returns the pastoral record if found in remote system
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return array
     */
    public function getPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        return $this->getDatabase()->getPastoral($pastoral);
    }

    /**
     * Creates a new pastoral record in the remot system
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return mixed
     */
    public function createPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        return $this->getDatabase()->createPastoral($pastoral);
    }

    /**
     * Updates an existing pastoral assessment
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return mixed
     */
    public function updatePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        return $this->getDatabase()->updatePastoral($pastoral);
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        return $this->getDatabase()->deletePastoral($pastoral);
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