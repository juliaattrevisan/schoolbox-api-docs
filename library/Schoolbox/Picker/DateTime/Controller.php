<?php
/**
 * Controller for datetime picker
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Picker\DateTime;

use Alaress\Web\Controller\Base;
use Calendar_Api_Factory;
use Alaress_Date_Format;
use External_TimetableInterface;
use InvalidArgumentException;
use Calendar\Event\Type\EventBase;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;

class Controller extends Base
{
    /** @var UserMapper */
    private $userMapper;

    /** @var TermMapper */
    private $termMapper;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

    /** @var External_TimetableInterface */
    private $timetableHandler;

    /**
     * Test URLS:
     * http://192.168.36.10/picker/datetime                                         <-- HTML for injecting into modal
     * http://192.168.36.10/picker/datetime/date/{date}                             <-- All appointments on a given date
     * http://192.168.36.10/picker/datetime/date/{date}/class/{classcode}           <-- All student for a given class and how many due work items they have that day
     */

    /**
     * Constructor
     *
     * @param UserMapper                  $userMapper
     * @param TermMapper                  $termMapper
     * @param UserAccessMapper            $userAccessMapper
     * @param AssessmentMapperProxy       $assessmentMapperProxy
     * @param External_TimetableInterface $timetableHandler
     */
    public function __construct(
        UserMapper $userMapper,
        TermMapper $termMapper,
        UserAccessMapper $userAccessMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        External_TimetableInterface $timetableHandler
    ) {
        $this->userMapper            = $userMapper;
        $this->termMapper            = $termMapper;
        $this->userAccessMapper      = $userAccessMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->timetableHandler      = $timetableHandler;
    }

    /**
     * Return the HTML picker for injecting into modal
     *
     * @param string $type    event or duework
     * @param string $element input field that calls a datepicker
     * @param int    $id      The ID number of the javascript picker
     * @param string $value   Default date to display
     */
    public function getPicker($type, $element, $id, $value)
    {
        $timetable = $this->getUserTimetable();
        $terms = $this->getTerms();

        try {
            $date = new Alaress_Date_Format($value, 'Y-m-d H:i:s');

        } catch (InvalidArgumentException $e) {
            // if parsing the given date failed we still have to respond with a picker, so falling back
            // to the current date is the only recourse. We do, however, log the failure if possible.
            $date = Alaress_Date_Format::now();
        }

        $timetableVars = [];

        // Don't bother calculating if the timetable is offline, we'll only display the calendar

        if (is_array($timetable)) {

            // Current term
            $term = $this->termMapper->findForDate($date);

            $timetableVars = [
                'term'          => $term->getId(),
                'isWeekend'     => $date->isWeekend() ? 1 : 0
            ];

            // Get all what we need to build proper timetable per campus
            foreach ($this->getUser()->getCampusCollection() as $campus) {
                $campusId = $campus->getId();

                $timetableDay = $timetable[$campusId]['dayCount'];

                if (!$timetableDay) {
                    // if there are no days, don't attempt to
                    continue;
                }

                $timetableVars['byCampus'][$campusId]['id'] = $campusId;

                // Loop through terms and set cycle start and end dates
                $cycles = [];

                foreach ($terms as $cycleTerm) {

                    $cycleTermActualStart = $this->getFirstTimetableDayForTerm($campus, $cycleTerm);

                    $cycleTermStartDate = $cycleTermActualStart['date'];
                    $cycleTermStartDay = $cycleTermActualStart['day'];

                    $cycleTermEndDate = $cycleTerm->getDisplayFinish();

                    $tempDate = clone $cycleTermStartDate;
                    $tempDay = $cycleTermStartDay;

                    $cycleDays = $timetableDay;
                    $currentCycle = 1;

                    while ($tempDate->compareDateOnly($cycleTermEndDate) <= 0) {

                        $cycles[$cycleTerm->getId()][$currentCycle][$tempDay] = $tempDate->toIso8601String();

                        if ($tempDay == $cycleDays) {
                            $currentCycle++;
                            $tempDay = 1;
                        } else {
                            $tempDay++;
                        }

                        do {
                            $tempDate->addDays(1);
                        } while ($tempDate->isWeekend());

                    }
                }

                $timetableVars['byCampus'][$campus->getId()]['cycles'] = json_encode($cycles);
            }
        }

        $displayFormatDate = $this->getConfigLoader()->get('date_local');
        $displayFormatTime = $displayFormatDate . ' ' . $this->getConfigLoader()->get('date_time');

        $this->getResponse()->content->set([
            'template' => '@pickerDatetime/picker.twig',
            'format' => 'nochrome',
            'data' => [
                'id' => $id,
                'value' => $date->toIso8601String(),
                'element' => $element,
                'type' => $type,
                'displayFormat' => $this->convertFormat($displayFormatDate) . ' ' . $this->convertFormat($displayFormatTime),
                'displayFormatDate' => $this->convertFormat($displayFormatDate),
                'weekStartDay' => $this->getConfigLoader()->get('calendar_start_day'),
                'terms' => $terms,

                // If the connector is offline, pass FALSE

                'timetable' => $timetable,
                'timetableVars' => $timetableVars
            ]
        ]);
    }


    /**
     * @param int    $year
     * @param int    $month
     * @param Folder $folder
     * @return void
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getHeat($year, $month, Folder $folder)
    {
        $heat = [];

        $start = new Alaress_Date_Format("$year-$month-1 00:00:00", 'Y-m-d H:i:s');

        $end = clone $start;
        $end = $end->endOfMonth();

        $students = $this->studentsDueWithinRange($start, $end, $folder);

        foreach ($students as $student) {
            foreach ($student['assessmentList'] as $assessment) {
                $heat[$assessment['duedate']][$assessment['id']] = true;
            }
        }

        $heat = array_map(function ($v) { return min(count($v), 5) * 20; }, $heat);

        $this->getResponse()->content->set($this->buildJsonOutput($heat));
    }


    /**
     * Converts schoolbox config date and time formats info flatpickr friendly
     *
     * @param string $format
     * @return string
     */
    public function convertFormat($format)
    {
        return strtr($format, [
            'd' => 'd', // day of month
            'S' => '', // not supported
            'D' => 'D', // Suffix for day of month
            'l' => 'l', // Day name in full
            'M' => 'M', // Month number with leading zero
            'n' => 'n', // Month number no leading zero
            'y' => 'y', // Year two digits
            'Y' => 'Y', // Year four digits
            'h' => 'h', // 01-12 not supported, use 1-12 hours
            'g' => 'h', // 1-12 hours
            'a' => 'K', // no lowercase am/pm supported unfortunately
            'A' => 'K', // AM/PM
            'H' => 'H', // 00 - 23 hours
            'G' => 'H', // 00 - 23 hours, no support for hour with no leading zero
            'i' => 'i', // minutes leading zero
            's' => 'S', // 00 - 59 seconds
        ]);

    }

    /**
     * Return an array of all timetables and campus data
     *
     * @return array
     */
    public function getUserTimetable()
    {
        $timetable  = [];
        $campusInfo = [];

        $user = $this->getUser();

        foreach ($user->getCampusCollection() as $campus) {

            $campusTimetable = $this->timetableHandler->getTimetableDefinition(null, $campus);

            if ($campusTimetable) {
                $campusInfo['campusId']   = $campus->getId();
                $campusInfo['campusName'] = $campus->getName();
                $campusInfo['campusCode'] = $campus->getExtTimetableCode();

                //Return campus and it's timetable in one array
                $timetable[$campus->getId()] = array_merge($campusInfo, $campusTimetable);

            } else {
                $timetable = false;
            }
        }

        return $timetable;
    }

    /**
     * Return all terms
     *
     * @return Collection|Term[]
     */
    public function getTerms()
    {
        return $this->termMapper->findAll([ 'order' => [ 'start' => 'desc' ] ]);
    }

    /**
     * Return a first timetable day for the first monday of a term
     *
     * @param Campus $campus
     * @param Term $term
     * @return array
     */
    public function getFirstTimetableDayForTerm(Campus $campus, Term $term)
    {
        $date = new Alaress_Date_Format($term->getStart());

        // We can't base calculations on a date that falls on a weekend
        // We'll get return a timetable day number for the following Monday
        $termActualStart = [];

        // Skip 1 day if Sunday, 2 days if Saturday
        $dateOffset = !$date->isWeekend() ? 0 : (8 % $date->getWeekDayNumber());

        if ($date->isWeekend()) {
            // It is Sunday
            $dateOffset = 1;
            // Or maybe Saturday
            if ($date->getWeekDayNumber() == 6) {
                $dateOffset = 2;
            }
        }

        $timetableDay = $this->timetableHandler->getDayNumber($campus, $date->addDays($dateOffset)->getUnixTimeStamp());
        $termActualStart['date'] = $date;
        $termActualStart['day'] = $timetableDay;

        return $termActualStart;
    }

    /**
     * Return JSON list of all appointments for a given date
     *
     * @param Alaress_Date_Format $date
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Web\Exception\InvalidStatusCode
     * @return void
     */
    public function getByDate(Alaress_Date_Format $date)
    {
        $appointments = [];

        // Get start/end of the date

        $start = $date->startOfDay();
        $end = clone $start;
        $end = $end->addDays(1);

        /** @var Calendar_Api_Factory $factory */
        $factory = $this->getDi()->get('calendarApiFactory');
        $api = $factory->produceForUser($this->getUser(), $this->getUser());

        $events = $api->getEvents($start->getUnixTimeStamp(), $end->getUnixTimeStamp());

        foreach ($events as $event) {
            /** @var $event EventBase */
            $appointments[] = [
                'displayDateTime' => $event->getEventSpan(),
                'title'           => $event->getTitle(),
                'start'           => $event->getStartTime()->toIso8601String(),
                'end'             => $event->getEndTime()->toIso8601String(),
                'allDay'          => $event->getAllday()
            ];
        }

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => [
                'appointments' => $appointments,
                'date' => $date->toIso8601String()
            ]
        ]);
    }



    /**
     * Return JSON list of students and how much due work that have for a given date and class code(s)
     *
     * @param Alaress_Date_Format $date (Date we are interested in)
     * @param Folder              $folder
     * @return void
     */
    public function getDueByDate(Alaress_Date_Format $date, $folder)
    {
        // Don't show other students' due work to anyone but staff
        if (!$this->getUser()->getRole()->isStaff()) {
            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => []
            ]);

            return;
        }

        $date = clone $date;
        $start = $date->startOfDay();
        $end = clone $start;
        $end = $end->endOfDay();

        // Spit this out in a JSON array
        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => [
                'students' => $this->studentsDueWithinRange($start, $end, $folder),
                'date' => $date->toIso8601String()
            ]
        ]);
    }


    /**
     * @param Alaress_Date_Format $start
     * @param Alaress_Date_Format $end
     * @param Folder              $folder
     * @return array
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    private function studentsDueWithinRange(Alaress_Date_Format $start, Alaress_Date_Format $end, $folder)
    {
        // Get all students for the class or the group

            $externalStudents = $this->userAccessMapper->findUsersInCategory(
                $folder,
                Role::STUDENT,
                UserAccess::ACCESS_READ,
                true
            );

        // Get a lookup array of external IDs for the students in this class

        $externalIds = [];

        foreach ($externalStudents as $studentCurrent) {
            $externalIds[] = $studentCurrent->getExternalId();
        }

        $studentList = $this->userMapper->findByColumn('synergy_id', $externalIds);
        // Iterate each student in the class

        $students = [];

        foreach ($studentList as $studentCurrent) {

            // Create a mock user object and find all assessments for this student
            $assessments = $this->assessmentMapperProxy->findAllByStudentBetweenDates($studentCurrent, true, $start, $end);
            // Count how many assessments are due on the specified date
            $assessmentsDue = \count($assessments);
            $assessmentList = [];
            foreach ($assessments as $assessment) {
                $assessmentList[] = [
                    'id'      => $assessment->getId(),
                    'title'   => $assessment->getTitle(),
                    'duedate' => $assessment->getDueAt()->getDay(),
                    'duetime' => $assessment->getDueAt()->getTimeOnly(),
                    'context' => $assessment->getBaseContext()->getFolder()->getName()
                ];
            }
            if ($assessmentsDue > 0) {
                $students[] = [
                    'id'             => $studentCurrent->getId(),                      // Required for link to student profile
                    'externalId'     => $studentCurrent->getExternalId(),      // Required for rendering student photo
                    'firstname'      => $studentCurrent->getFirstname(),
                    'lastname'       => $studentCurrent->getLastname(),
                    'assessmentList' => $assessmentList,
                    'assessmentsDue' => $assessmentsDue                    // Number of assessments they have due on the date
                ];
            }
        }

        // Sort by the number of assignments due

        $assessmentsDue = [];

        foreach ($students as $key => $row) {
            $assessmentsDue[$key] = $row['assessmentsDue'];
        }

        array_multisort($assessmentsDue, SORT_DESC, $students);
        $students = array_slice($students, 0, 5);

        return $students;
    }
}
