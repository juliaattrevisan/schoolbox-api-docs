<?php
/**
 * Hydrator for campus for Meriden users.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Campus implements Synchronise_Base_IHydrator {
    /**
     * The field to retrieve the campus from.
     *
     * @var string
     */
    protected $field = 'StudentCampus';

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $campus = $this->getDefaultCampusFromUser($externalUser);
        if ($campus) {
            $newUser['campus']        = array($campus);
            $newUser['defaultCampus'] = $campus;
        }

        if($externalUser['StudentYearLevel'] < 8 ||$externalUser['StudentYearLevel'] > 12)
            throw new Exception('Not active user');

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
        switch (strtoupper(trim($externalUser[$this->field]))) {
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
