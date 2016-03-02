<?php
/**
 * Year sync for students
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Synchronise_External_Cis_Hydrator_Student_Year implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser['year'] = $this->getYear($externalUser['StudentYearCode']);
        return $newUser;
    }

    /**
     * Gets year from year code
     *
     * @param  string $yearCode
     * @return int
     */
    protected function getYear($yearCode)
    {
        switch ($yearCode) {
            // TK Junior
            case 'TKECE':
                return 'ECE';
            case 'TKJK':
                return 'JK';
            case 'TKSK':
                return 'SK';
            case 'TKG1':
                return 1;
            case 'TKG2':
                return 2;
            case 'TKG3':
                return 3;
            case 'TKG4':
                return 4;
            case 'TKG5':
                return 5;
            case 'TKG6':
                return 6;


            // Junior Lakeside
            case 'ECE':
                return 'ECE';
            case 'JK':
                return 'JK';
            case 'SK':
                return 'SK';
            case 'G1':
                return 1;
            case 'G2':
                return 2;
            case 'G3':
                return 3;
            case 'G4':
                return 4;
            case 'G5':
                return 5;
            case 'G6':
                return 6;

            case 'G7':
                // MYP Grade 7
                return 7;
            case 'G8':
                // MYP Grade 8
                return 8;
            case 'G9':
                // MYP Grade 9
                return 9;
            case 'G10':
                // MYP Grade 10
                return 10;
            case 'G11':
                // IB Grade 11
                return 11;
            case 'G12':
                // IB Grade 12
                return 12;
            case 'OSSD11':
                // OSSD Grade 11
                return 11;
            case 'OSSD12':
                // OSSD Grade 12
                return 12;
        }
        return 0;
    }
}
