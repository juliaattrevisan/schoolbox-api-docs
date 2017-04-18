<?php
/**
 * Exporter for the timetable array
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External;

use External_TimetableInterface;
use Schoolbox\Campus;
use Schoolbox\User;

class TimetableArrayExporter
{
    /**
     * @var External_TimetableInterface
     */
    protected $timetableHandler;

    public function __construct(External_TimetableInterface $timetableHandler)
    {
        $this->timetableHandler = $timetableHandler;
    }

    public function export(User $user) {
        $campusList = [];
        foreach ($user->getCampusCollection() as $campus) {
            /** @var Campus $campus */
            $campusId    = $campus->getId();
            $campusArray = array(
                'id'         => $campusId,
                'name'       => $campus->getName(),
                'days'       => 0,
                'periodList' => array(),
            );

            $timetable = $this->timetableHandler->getTimetableDefinition(null, $campus);

            // Even if this fails to get a period list, it will get a day count
            $timetableDayNames = [];
            for ($i = 1; $i <= $timetable['dayCount']; $i++) {
                $timetableDayNames[] = [
                    'number' => $i,
                    'name'   => $this->timetableHandler->getDayName($i),
                ];
            }
            $campusArray['days'] = $timetableDayNames;
            $campusList[$campusId] = $campusArray;
        }
        return $campusList;
    }
}
