<?php
/**
 * Hydrates student's username from their student ID.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_Student_UsernameFromId implements Synchronise_Base_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser['username'] = $externalUser['StudentID'];
        return $newUser;
    }
}
