<?php

/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Synchronise_Synergetic_Kings_Hydrator_ParentUserName implements Synchronise_Base_IHydrator
{

    /**
     * This method looks at the default email code and determines which email address to use.
     * Note that this will work on Synergetic Staff.
     *
     * @param  array $externalUser A result from the external database.
     * @param  array $newUser The current result that will be inserted into
     *                             Schoolbox.
     *                             This result could have come from a hydrate()
     *                             call on another class that implements this
     *                             interface.
     * @return array The result to insert into Schoolbox,
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['username'] = $externalUser['NetworkLogin'];
    
        return $newUser;
    }

}
