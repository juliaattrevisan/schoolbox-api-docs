<?php
/**
 * This interface defines how to store pastoral data in a remote SIS
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

use \Schoolbox\Pastoral\Pastoral;


interface External_PastoralInterface
{

    /**
     * Returns the pastoral record if found in remote system
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return array
     */
    public function getPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral);

    /**
     * Creates a new pastoral record in the remote system
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return bool
     */
    public function createPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral);

    /**
     * Updates an existing pastoral record
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return bool
     */
    public function updatePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral);

    /**
     * Deletes an existing pastoral record
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return bool
     */
    public function deletePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral);

}


