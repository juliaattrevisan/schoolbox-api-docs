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
            // Somehow parse $timetable into a list of names, start and end, and
            // the next day they occur.
            // times always 12-hour, so convert to 24-hour time for consistency
            $campusArray['days'] = $timetable['dayCount'];
            if (!empty($timetable)) {
                foreach ($timetable['desc'] as $i => $name) {
                    $campusArray['periodList'][] = array(
                        'index' => $i,
                        'desc'  => $name,
                        'start' => date('H:i', strtotime($timetable['start'][$i])),
                        'end'   => date('H:i', strtotime($timetable['end'][$i])),
                    );
                }
                $campusList[$campusId] = $campusArray;
            }
        }
        return $campusList;
    }
}
