<?php
/**
 * Interface for hydrator classes in sync scripts.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

interface Synchronise_Base_IHydrator {
    const DEFAULT_CAMPUS        = 1;
    const DEFAULT_ADMIN_GROUP   = 1;
    const DEFAULT_STAFF_GROUP   = 2;
    const DEFAULT_STUDENT_GROUP = 3;
    const DEFAULT_PARENT_GROUP  = 4;

    /**
     * Converts data from the format from external database to the format used
     * in Schoolbox.
     *
     * This method should modify and return $newUser.
     *
     * @param  array $externalUser A result from the external database.
     * @param  array $newUser      The current result that will be inserted into
     *                             Schoolbox.
     *                             This result could have come from a hydrate()
     *                             call on another class that implements this
     *                             interface.
     * @return array The result to insert into Schoolbox,
     */
    public function hydrate($externalUser, $newUser);
}