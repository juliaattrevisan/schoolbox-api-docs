<?php
/**
 * This interface defines how to store pastoral data in a remote SIS
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

use \Schoolbox\Pastoral\Model as PastoralModel;

interface External_PastoralInterface
{

    /**
     * Returns the pastoral record if found in remote system
     *
     * @param PastoralModel $pastoral
     * @return array
     */
    public function getPastoral(PastoralModel $pastoral);

    /**
     * Creates a new pastoral record in the remote system
     *
     * @param PastoralModel $pastoral
     * @return bool
     */
    public function createPastoral(PastoralModel $pastoral);

    /**
     * Updates an existing pastoral record
     *
     * @param PastoralModel $pastoral
     * @return bool
     */
    public function updatePastoral(PastoralModel $pastoral);

    /**
     * Deletes an existing pastoral record
     *
     * @param PastoralModel $pastoral
     * @return bool
     */
    public function deletePastoral(PastoralModel $pastoral);

}


