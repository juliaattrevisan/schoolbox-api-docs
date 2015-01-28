<?php
/**
 * Hydrator for username for Toowoomba staff.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_Username_Staff implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['username'] = strtolower(
            sprintf(
                '%s%s',
                mb_substr($externalUser['StaffPreferredName'], 0, 1, 'UTF-8'),
                $externalUser['StaffSurname']
            )
        );
        return $newUser;
    }
}
