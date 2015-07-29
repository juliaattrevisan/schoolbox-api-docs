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
