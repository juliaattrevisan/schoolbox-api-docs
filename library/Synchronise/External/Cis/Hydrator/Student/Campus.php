<?php
/**
 * Campus sync for students
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class Synchronise_External_Cis_Hydrator_Student_Campus implements Synchronise_Base_IHydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        // Currently HS is the only campus
        $campusMap = [
            'HS' => 3,
        ];
        $campusList = [];
        foreach ($externalUser['StudentCampus'] as $campus) {
            if (array_key_exists($campus, $campusMap)) {
                $campusList[] = $campus;
            }
        }
        if (empty($campusList)) {
            throw new Exception('Students without a campus are not imported');
        }
        $newUser['campus']        = $campusList;
        $newUser['defaultCampus'] = array_shift($campusList);
        return $newUser;
    }
}
