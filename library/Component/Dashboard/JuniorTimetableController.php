<?php

/**
 * Component_Dashboard_JuniorTimetableController
 *
 * A custom component for Arden SB-6491
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Component_Dashboard_JuniorTimetableController extends Component_Dashboard_TimetableController
{

    /**
     * Entirely ignore classes which have no code
     * such as recess, lunch, etc.
     *
     * Change to true to include recess, lunch, etc
     * once again
     *
     * @var bool
     */
    private $includeNonclasses = false;

    /**
     * The html file to load
     *
     * @var string
     */
    protected $templateFile = 'juniorTimetable';

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        $this->generateTimetables();
        $this->colourList = Timetable_ColourMapper::getInstance()->findAll();
        return true;
    }

    /**
     * Stack up a whole bunch of timetables for each campii
     */
    private function generateTimetables()
    {
        $timetableHandler = new \External_Timetable();

        // get current day and period.
        // Don't output a timetable if it has the same code as another that has
        // already been added.
        $checked_campus_list = array();

        foreach ($this->getUser()->getCampusCollection() as $campus) {
            if ($campus instanceof Schoolbox\Campus) {
                $campus_code = $campus->getExtTimetableCode();
                $campus_id = $campus->getId();

                if (!isset($checked_campus_list[$campus_code])) {
                    $checked_campus_list[$campus_code] = true;
                    $day_number                        = $timetableHandler->getDayNumber($campus);

                    if ($day_number) {
                        $timetable = $timetableHandler->getTimetableForDate(
                            $this->getUser()->getExternalId(),
                            $this->getUser()->getId(),
                            $campus,
                            Alaress_Date_Format::now()
                        );

                        $processed = $this->condense($timetable);

                        $this->timetable[$campus_id] = $processed['condensed'];
                        $this->dayNumber[$campus_id] = $day_number;
                        $this->dayName[$campus_id]   = $timetableHandler->getDayName($day_number);
                    }
                }
            }
        }
    }

    /**
     * Decide on a period code, for event stack
     *
     * @param array $period
     * @return string
     */
    private function getCode($period)
    {

        /* Special cases for recess, lunch, etc which have no codes
         */
        if (!isset($period['code'])) {
            $code = "_nonclass {$period['periodName']}";
        } else {
            $code = $period['code'];
        }

        return $code;
    }

    /**
     * Condense adjacent timetable periods
     *
     * @param array $timetable
     * @return array
     */
    private function condense($timetable)
    {
        /* Condensation
         */
        $condensedClass      = false;
        $allCondensedClasses = array();

        /* A stack of classcodes that were condensed, in order
         * This is used to manage some edge cases at the end
         */
        $lastProcessed = array();

        /* Event stack, for debugging/diagnosis
         */
        $stack = array();

        for ($i = 0; $i < count($timetable); $i++) {
            /* Make a gigantic assumption
             * formerly: $period = $timetable[$i];
             */
            if ($timetable[$i]['periodSequence'] == 2) {
                if ($this->getIncludeNonclasses()) {
                    $period = $timetable[$i];
                }
                /* Skip nonclasses
                 */
                else {
                    continue;
                }
            } else {
                /* Start restructuring
                 */
                if (count($timetable[$i]['class'])) {
                    /* Fix for SB-7048 prevents you from using first element
                     * in the stack directly, have to array_values first
                     */
                    $period = array_shift(array_values($timetable[$i]['class']));
                    $period['periodStart']    = $timetable[$i]['periodStart'];
                    $period['periodEnd']      = $timetable[$i]['periodEnd'];
                    $period['periodNumber']   = $timetable[$i]['periodNumber'];
                    $period['periodSequence'] = $timetable[$i]['periodSequence'];
                }
                /* Hide periodSequence 1 periods which have no classes
                 * (Hide spares)
                 */
                else {
                    continue;
                }
            }

            /* Initial condition
             */
            if ($condensedClass === false) {
                $condensedClass = $period;

                $stack[] = "Initial condensed class is {$this->getCode($period)}<br>";
            }
            /* Condense
             */
            else {
                if ($condensedClass['code'] == $period['code']) {
                    $stack[] = "Updating periodEnd to {$period['periodEnd']}<br>";

                    $condensedClass['periodEnd'] = $period['periodEnd'];
                } else {
                    if ($this->getIncludeNonclasses()) {
                        $stack[]         = "Adding {$this->getCode($condensedClass)}<br>";
                        $lastProcessed[] = $condensedClass['code'];

                        $allCondensedClasses[] = $condensedClass;
                    } elseif (isset($condensedClass['code'])) {
                        $stack[]         = "Adding {$this->getCode($condensedClass)}<br>";
                        $lastProcessed[] = $condensedClass['code'];

                        $allCondensedClasses[] = $condensedClass;
                    }

                    $condensedClass = $period;

                    $stack[] = "Current condensed class is {$period['code']}<br>";
                }
            }
        }

        /* There are a number of edge cases relating to the processing of the
         * final class of the day. Each of these edge cases causes the
         * $condensedClass to be set up but never added, and they are
         * consequently excluded from $allCondensedClasses stack
         *
         * + Last period in the day is the same as the second-last period
         *   in the day. This falls under "$lastCode == $period[code]" clause
         *   and may be accidentally excluded as a result.
         *
         * + Last period in the day is different to the second-last period
         *   in the day. This falls under the else{} near "$lastCode == $period[code]"
         *   and may be accidentally excluded as a result.
         *
         * + Last period in the day is one or more empty periodSequence 1 classes
         *   (spares). This falls under the "else { continue; }" part and may
         *   be accidentally excluded as a result.
         *
         * + Last period in the day is one or more empty periodSequence 2 classes
         *   (unknown). This falls under the "else { continue; }" part and may
         *   be accidentally excluded as a result. I haven't actually seen this
         *   yet and still needs to be verified with production data.
         */
        if ($condensedClass['code'] != end($lastProcessed)) {
            if ($this->getIncludeNonclasses() || isset($condensedClass['code'])) {
                $stack[]         = "Adding {$this->getCode($condensedClass)}<br>";
                $lastProcessed[] = $condensedClass['code'];

                $allCondensedClasses[] = $condensedClass;
            }
        }

        /* Reconstruct bucket structure
         */
        $processed = array();

        $i = 1;

        /* Clear out the period name, beacuse they will now be combined
         * and individual headings no longer mean anything
         */
        foreach ($allCondensedClasses as $currentClass) {
            $temp = $currentClass;

            /* Force rendering of nonclasses (periodSequence 2)
             * This should only run when includeNonClasses is set to true
             * as that accumulates nonclasses; setting it to false will
             * exclude them before getting here
             */
            if ($currentClass['periodSequence'] == 2) {
                $temp['description'] = $currentClass['periodName'];
                $temp['code']        = $currentClass['periodName'];
            }

            unset($temp['class']);
            unset($temp['periodName']);
            unset($temp['periodStart']);
            unset($temp['periodEnd']);
            unset($temp['periodNumber']);
            unset($temp['periodSequence']);

            $record = array(
                'periodName'     => '',
                'periodStart'    => $currentClass['periodStart'],
                'periodEnd'      => $currentClass['periodEnd'],
                'periodNumber'   => $currentClass['periodNumber'],
                'periodSequence' => 1,
                'class'          => array(
                    $temp
                )
            );

            array_push($processed, $record);

            $i++;
        }

        return array(
            'condensed' => $processed,
            'stack'     => $stack
        );
    }

    /**
     * Sample hardcoded data
     */
    private function tempdata()
    {
        $timetable[0]['periodName']     = 'The First Period of the Day';
        $timetable[0]['periodStart']    = '8:30am';
        $timetable[0]['periodEnd']      = '9:30am';
        $timetable[0]['periodNumber']   = '1';
        $timetable[0]['periodSequence'] = '1';
        $timetable[0]['description']    = 'Chemistry';
        $timetable[0]['code']           = 'CH123';
        $timetable[0]['campus']         = 'S';
        $timetable[0]['room']           = 'B002';
        $timetable[0]['staffID']        = '165';

        $timetable[1]['periodName']     = 'Per 2';
        $timetable[1]['periodStart']    = '9:30am';
        $timetable[1]['periodEnd']      = '10:30am';
        $timetable[1]['periodNumber']   = '2';
        $timetable[1]['periodSequence'] = '1';
        $timetable[1]['description']    = 'Chemistry';
        $timetable[1]['code']           = 'CH123';
        $timetable[1]['campus']         = 'S';
        $timetable[1]['room']           = 'B205';
        $timetable[1]['staffID']        = '48495';

        $timetable[2]['periodName']     = 'Recess';
        $timetable[2]['periodStart']    = '10:30am';
        $timetable[2]['periodEnd']      = '10:45am';
        $timetable[2]['periodNumber']   = '2';
        $timetable[2]['periodSequence'] = '2';

        $timetable[3]['periodName']     = 'Per 3';
        $timetable[3]['periodStart']    = '10:45am';
        $timetable[3]['periodEnd']      = '11:45am';
        $timetable[3]['periodNumber']   = '3';
        $timetable[3]['periodSequence'] = '1';
        $timetable[3]['description']    = 'English 3 4';
        $timetable[3]['code']           = 'E34';
        $timetable[3]['campus']         = 'S';
        $timetable[3]['room']           = 'Q105';
        $timetable[3]['staffID']        = '323';

        $timetable[4]['periodName']     = 'Per 4';
        $timetable[4]['periodStart']    = '11:45am';
        $timetable[4]['periodEnd']      = '12:45pm';
        $timetable[4]['periodNumber']   = '4';
        $timetable[4]['periodSequence'] = '1';
        $timetable[4]['description']    = 'Information Technology';
        $timetable[4]['code']           = 'IT444';
        $timetable[4]['campus']         = 'S';
        $timetable[4]['room']           = '';
        $timetable[4]['staffID']        = '165';

        $timetable[5]['periodName']     = 'Per 5';
        $timetable[5]['periodStart']    = '12:45pm';
        $timetable[5]['periodEnd']      = '1:30pm';
        $timetable[5]['periodNumber']   = '4';
        $timetable[5]['periodSequence'] = '1';
        $timetable[5]['description']    = 'Information Technology';
        $timetable[5]['code']           = 'IT444';
        $timetable[5]['campus']         = 'S';
        $timetable[5]['room']           = '';
        $timetable[5]['staffID']        = '165';

        $timetable[6]['periodName']     = 'Per 6';
        $timetable[6]['periodStart']    = '1:30pm';
        $timetable[6]['periodEnd']      = '2:30pm';
        $timetable[6]['periodNumber']   = '5';
        $timetable[6]['periodSequence'] = '1';
        $timetable[6]['description']    = 'German C';
        $timetable[6]['code']           = 'G12';
        $timetable[6]['campus']         = 'S';
        $timetable[6]['room']           = 'Q004';
        $timetable[6]['staffID']        = '228';

        $timetable[7]['periodName']     = 'Per 7';
        $timetable[7]['periodStart']    = '2:30pm';
        $timetable[7]['periodEnd']      = '3:30pm';
        $timetable[7]['periodNumber']   = '6';
        $timetable[7]['periodSequence'] = '1';

        /* Check an edge case
         */
        $prepend['periodName']     = 'Morning Shift';
        $prepend['periodStart']    = '7:00am';
        $prepend['periodEnd']      = '8:00am';
        $prepend['periodNumber']   = '2';
        $prepend['periodSequence'] = '2';

        $prepend2['periodName']     = 'Morning Shift';
        $prepend2['periodStart']    = '8:00am';
        $prepend2['periodEnd']      = '8:10am';
        $prepend2['periodNumber']   = '2';
        $prepend2['periodSequence'] = '2';

        array_unshift($timetable, $prepend2);
        array_unshift($timetable, $prepend);

        return $timetable;
    }

    /* Getters/Setters
     */

    /**
     * Set include non classes
     *
     * @param boolean $includeNonclasses
     */
    private function setIncludeNonclasses($includeNonclasses)
    {
        $this->includeNonclasses = $includeNonclasses;
    }

    /**
     * Get include non classes
     *
     * @return boolean
     */
    private function getIncludeNonclasses()
    {
        return $this->includeNonclasses;
    }
}
