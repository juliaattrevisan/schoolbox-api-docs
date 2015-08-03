<?php
/**
 * Campus sync for parents
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Synchronise_External_Cis_Hydrator_Parent_Campus implements Synchronise_Base_IHydrator
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
        // Put parents in all campuses their students are in
        $campusList = [];

        // Parent sync doesn't load campuses, need to load via student ID
        $studentIds = array_map(
            function ($v) {
                return 'S-' . $v;
            },
            $externalUser['StudentContactStudentIds']
        );
        foreach (UserMapper::getInstance()->findByColumn('synergy_id', $studentIds) as $student) {
            /** @var User $student */
            foreach ($student->getCampusCollection() as $campus) {
                $campusList[] = $campus->getId();
            }
        }
        if (empty($campusList)) {
            throw new Exception('Parents without a campus are not imported');
        }
        $newUser['campus']        = $campusList;
        $newUser['defaultCampus'] = array_shift($campusList);
        return $newUser;
    }
}
