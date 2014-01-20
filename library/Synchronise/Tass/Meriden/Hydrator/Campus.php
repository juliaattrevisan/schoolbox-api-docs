<?php
/**
 * Hydrator for campus for Meriden users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Campus implements Synchronise_Base_Strategy_IHydrator {
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $campus = $this->getDefaultCampusFromUser($externalUser);
        if ($campus) {
            $newUser['campus']        = array($campus);
            $newUser['defaultCampus'] = $campus;
        }
        return $newUser;
    }

    /**
     * Gets campus according to the following map:
     *
     * * SS => 1 (Senior School)
     * * JS => 2 (Junior School)
     *
     * @param  array $externalUser
     * @return int
     */
    public function getDefaultCampusFromUser($externalUser) {
        switch (strtoupper(trim($externalUser['StudentCampus']))) {
            case 'JS':
                return 1;
            case 'SS':
                return 2;
            default:
                break;
        }
        return 0;
    }
}