<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\User;

/**
 * Schooledge implementation of user retrieve methods.
 */
class External_Schooledge_User extends External_Humanedge_User implements External_UserInterface
{
    /**
     * Returns list of subjects the user is enrolled in
     *
     * @param User $user
     * @return array
     */
    public function getSubjectList(User $user)
    {
        // Query $this->database instead of the A+ database
        return [];
    }
}
