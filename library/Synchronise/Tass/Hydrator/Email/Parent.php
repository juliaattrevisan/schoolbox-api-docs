<?php
/**
 * Hydrator for email for TASS parents.
 *
 * TASS email field can have multiple emails, each separated by a ;
 * character. I'm going to assume only the first should be used.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Hydrator_Email_Parent implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $emailList = preg_split('/[;\\s]+/', $externalUser['StudentContactDefaultEmail']);
        $newUser['alt_email'] = $emailList[0];
        return $newUser;
    }
}
