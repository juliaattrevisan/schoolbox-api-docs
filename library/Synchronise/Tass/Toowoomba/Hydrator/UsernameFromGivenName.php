<?php
/**
 * Hydrates student's username from their given name.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_UsernameFromGivenName implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['username'] = strtolower(
            sprintf(
                '%s.%s',
                $externalUser['StudentPreferred'],
                $externalUser['StudentSurname']
            )
        );
        return $newUser;
    }
}
