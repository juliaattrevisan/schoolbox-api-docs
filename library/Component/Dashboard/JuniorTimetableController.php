<?php
/**
 * Component_Dashboard_JuniorTimetableController
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class Component_Dashboard_JuniorTimetableController extends Component_Dashboard_ControllerAbstract {

    /**
     * A collection of all the timetables
     *
     * @var Schoolbox\Collection
     */
    private $allTimetables = null;

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return void
     */
    public function execute() {
        return true;
    }

    /**
     * Outputs HTML template defined by config variable 'template'
     *
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle) {
        if(!parent::loadTemplate($templateHandle, 'juniorTimetable')) {
            return false;
        }

        $this->parseConfig();

        /* Hook method
         */
        $this->setAllTimetables(new \Schoolbox\Collection());

        $timetableHandler = new \External_Timetable();

        // get current day and period.
        // Don't output a timetable if it has the same code as another that has
        // already been added.
        $checked_campus_list = array();

        foreach ($_SESSION['campus'] as $campus_id) {
            $campus = Schoolbox\CampusMapper::getInstance()->findById($campus_id);

            if ($campus instanceof Schoolbox\Campus) {
                $campus_code = $campus->getExtTimetableCode();

                if (!isset($checked_campus_list[$campus_code])) {
                    $checked_campus_list[$campus_code] = true;
                    $day_number = $timetableHandler->getDayNumber($campus_id);

                    if ($day_number) {
                        $timetable = $timetableHandler->getDayTimetable(
                            $_SESSION['user']->getExternalId(),
                            $_SESSION['user']->getId(),
                            $campus_id,
                            $day_number
                        );

                        $processed = $this->condense($timetable);

                        $this->getAllTimetables()->add($processed['condensed']);

                        new \Alaress_ThreeProbe($timetable, $processed['stack'], $this->getAllTimetables());
                    }
                }
            }
        }

        return true;
    }

    /**
     * Condense adjacent timetable periods
     *
     * @param $timetable
     * @return array
     */
    private function condense($timetable) {

        /* Condense each class code. Due to Third Dimension I can't assume that
         * classes appear in each array index in the same place.
         */
        $lastCode = false;

        /* Condensation
         */
        $condensedClass = false;
        $allCondensedClasses = array();

        /* Event stack, for debugging
         */
        $stack = array();

        for ($i = 0; $i < count($timetable); $i++) {
            $period = $timetable[$i];

            /* Special cases for recess, lunch, etc which have no codes
             */
            if (!isset($period['code'])) {
                $period['code'] = "_nonclass {$period['periodName']}";
            }

            /* Initial condition
             */
            if ($condensedClass === false) {
                $lastCode = $period['code'];
                $condensedClass = $period;

                $stack[] = "Initial condensed class is {$period['code']}<br>";
            }
            /* Condense
             */
            else {
                if ($lastCode == $period['code']) {
                    $stack[] = "Updating periodEnd to {$period['periodEnd']}<br>";

                    $condensedClass['periodEnd'] = $period['periodEnd'];
                }
                else {
                    $stack[] = "Adding {$condensedClass['code']}<br>";

                    $allCondensedClasses[] = $condensedClass;

                    $condensedClass = $period;

                    $stack[] = "Current condensed class is {$period['code']}<br>";

                    /* If this is the last period in the timetable, add it. This can
                     * occur if the last element in $timetable is a nonclass that
                     * didn't match the one just added
                     */
                    if ($i == (count($timetable)-1)) {
                        $allCondensedClasses[] = $condensedClass;

                        $stack[] = "Adding {$condensedClass['code']}<br>";
                    }
                }
            }

            $lastCode = $period['code'];
        }

        return array(
            'condensed' => $allCondensedClasses,
            'stack' => $stack
        );
    }

    /**
     * Sample hardcoded data
     */
    private function tempdata() {
        $timetable[0]['periodName'] = 'The First Period of the Day';
        $timetable[0]['periodStart'] = '8:30am';
        $timetable[0]['periodEnd'] = '9:30am';
        $timetable[0]['periodNumber'] = '1';
        $timetable[0]['periodSequence'] = '1';
        $timetable[0]['description'] = 'Chemistry';
        $timetable[0]['code'] = 'CH123';
        $timetable[0]['campus'] = 'S';
        $timetable[0]['room'] = 'B002';
        $timetable[0]['staffID'] = '165';

        $timetable[1]['periodName'] = 'Per 2';
        $timetable[1]['periodStart'] = '9:30am';
        $timetable[1]['periodEnd'] = '10:30am';
        $timetable[1]['periodNumber'] = '2';
        $timetable[1]['periodSequence'] = '1';
        $timetable[1]['description'] = 'Chemistry';
        $timetable[1]['code'] = 'CH123';
        $timetable[1]['campus'] = 'S';
        $timetable[1]['room'] = 'B205';
        $timetable[1]['staffID'] = '48495';

        $timetable[2]['periodName'] = 'Recess';
        $timetable[2]['periodStart'] = '10:30am';
        $timetable[2]['periodEnd'] = '10:45am';
        $timetable[2]['periodNumber'] = '2';
        $timetable[2]['periodSequence'] = '2';

        $timetable[3]['periodName'] = 'Per 3';
        $timetable[3]['periodStart'] = '10:45am';
        $timetable[3]['periodEnd'] = '11:45am';
        $timetable[3]['periodNumber'] = '3';
        $timetable[3]['periodSequence'] = '1';
        $timetable[3]['description'] = 'English 3 4';
        $timetable[3]['code'] = 'E34';
        $timetable[3]['campus'] = 'S';
        $timetable[3]['room'] = 'Q105';
        $timetable[3]['staffID'] = '323';

        $timetable[4]['periodName'] = 'Per 4';
        $timetable[4]['periodStart'] = '11:45am';
        $timetable[4]['periodEnd'] = '12:45pm';
        $timetable[4]['periodNumber'] = '4';
        $timetable[4]['periodSequence'] = '1';
        $timetable[4]['description'] = 'Information Technology';
        $timetable[4]['code'] = 'IT444';
        $timetable[4]['campus'] = 'S';
        $timetable[4]['room'] = '';
        $timetable[4]['staffID'] = '165';

        $timetable[5]['periodName'] = 'Per 5';
        $timetable[5]['periodStart'] = '12:45pm';
        $timetable[5]['periodEnd'] = '1:30pm';
        $timetable[5]['periodNumber'] = '4';
        $timetable[5]['periodSequence'] = '1';
        $timetable[5]['description'] = 'Information Technology';
        $timetable[5]['code'] = 'IT444';
        $timetable[5]['campus'] = 'S';
        $timetable[5]['room'] = '';
        $timetable[5]['staffID'] = '165';

        $timetable[6]['periodName'] = 'Per 6';
        $timetable[6]['periodStart'] = '1:30pm';
        $timetable[6]['periodEnd'] = '2:30pm';
        $timetable[6]['periodNumber'] = '5';
        $timetable[6]['periodSequence'] = '1';
        $timetable[6]['description'] = 'German C';
        $timetable[6]['code'] = 'G12';
        $timetable[6]['campus'] = 'S';
        $timetable[6]['room'] = 'Q004';
        $timetable[6]['staffID'] = '228';

        $timetable[7]['periodName'] = 'Per 7';
        $timetable[7]['periodStart'] = '2:30pm';
        $timetable[7]['periodEnd'] = '3:30pm';
        $timetable[7]['periodNumber'] = '6';
        $timetable[7]['periodSequence'] = '1';

        /* Check an edge case
         */
        $prepend['periodName'] = 'Morning Shift';
        $prepend['periodStart'] = '7:00am';
        $prepend['periodEnd'] = '8:00am';
        $prepend['periodNumber'] = '2';
        $prepend['periodSequence'] = '2';

        $prepend2['periodName'] = 'Morning Shift';
        $prepend2['periodStart'] = '8:00am';
        $prepend2['periodEnd'] = '8:10am';
        $prepend2['periodNumber'] = '2';
        $prepend2['periodSequence'] = '2';

        array_unshift($timetable, $prepend2);
        array_unshift($timetable, $prepend);

        return $timetable;
    }

    /* Getters/Setters
     */
    
    /**
     * Set allTimetables
     *
     * @param \Schoolbox\Collection $allTimetables
     */
    private function setAllTimetables($allTimetables)
    {
        $this->allTimetables = $allTimetables;
    }

    /**
     * Get allTimetables
     * @return \Schoolbox\Collection
     */
    private function getAllTimetables()
    {
        return $this->allTimetables;
    }
}