<?php
/**
 * Hydrator for campus for Toowoomba users.
 *
 * Currently, it's always 1 (TACAPS)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Toowoomba_Hydrator_Campus implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $campus = $this->getDefaultCampusFromUser($externalUser);
        if ($campus) {
            $newUser['campus']        = array($campus);
            $newUser['defaultCampus'] = $campus;
        }
        return $newUser;
    }

    /**
     * @param  array $externalUser
     * @return int
     */
    public function getDefaultCampusFromUser($externalUser)
    {
        return 1;
    }
}
