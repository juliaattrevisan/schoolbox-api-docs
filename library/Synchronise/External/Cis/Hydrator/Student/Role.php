<?php
/**
 * Role sync for students
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Synchronise_External_Cis_Hydrator_Student_Role implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['group_id'] = $this->getRole($externalUser['StudentYearCode']);
        return $newUser;
    }

    /**
     * Gets role from year code
     *
     * @param  string $yearCode
     * @return int
     */
    protected function getRole($yearCode)
    {
        switch ($yearCode) {

            // TK Junior
            case 'TKECE':
                return 26;
            case 'TKJK':
                return 24;
            case 'TKSK':
                return 25;
            case 'TKG1':
                return 27;
            case 'TKG2':
                return 28;
            case 'TKG3':
                return 29;
            case 'TKG4':
                return 30;
            case 'TKG5':
                return 31;
            case 'TKG6':
                return 32;


            // Junior Lakeside
            case 'ECE':
                return 7;
            case 'JK':
                return 8;
            case 'SK':
                return 9;
            case 'G1':
                return 10;
            case 'G2':
                return 11;
            case 'G3':
                return 12;
            case 'G4':
                return 13;
            case 'G5':
                return 14;
            case 'G6':
                return 15;

            // Senior Lakeside
            case 'G7':
                // MYP Grade 9
                return 16;
            case 'G8':
                // MYP Grade 10
                return 17;
            case 'G9':
                // MYP Grade 9
                return 18;
            case 'G10':
                // MYP Grade 10
                return 19;
            case 'G11':
                // IB Grade 11
                return 20;
            case 'G12':
                // IB Grade 12
                return 21;
            case 'OSSD11':
                // OSSD Grade 11
                return 20;
            case 'OSSD12':
                // OSSD Grade 12
                return 21;
        }
        return 0;
    }
}
