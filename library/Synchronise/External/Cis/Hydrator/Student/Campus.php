<?php
/**
 * Campus sync for students
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Campus;
use Schoolbox\CampusMapper;

class Synchronise_External_Cis_Hydrator_Student_Campus implements Synchronise_Base_IHydrator
{
    /**
     * Mapping of campus codes to campus IDs
     *
     * @var array key: campus code, value: campus ID
     */
    protected $campusMap = [];

    /**
     * Constructor - loads the campus mapping for this object.
     *
     * @param array $campusList a list of campus codes that are able to be hydrated
     */
    public function __construct($campusList)
    {
        // Load the campus map
        foreach (
            CampusMapper::getInstance()->findByColumn(
                'ext_timetable_code',
                $campusList
            )
            as $campus
        ) {
            /** @var Campus $campus */
            $this->campusMap[$campus->getExtTimetableCode()] = $campus->getId();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        // Currently HS is the only campus
        $campusList = [];
        foreach ($externalUser['StudentCampus'] as $campus) {
            if (array_key_exists($campus, $this->campusMap)) {
                $campusList[] = $this->campusMap[$campus];
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
