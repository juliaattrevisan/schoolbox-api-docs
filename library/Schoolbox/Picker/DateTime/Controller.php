<?php
/**
 * Controller for datetime picker
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Picker\DateTime;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Calendar_Api_Factory;
use External_Timetable;
use InvalidArgumentException;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

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

    /** @var ConfigLoader */
    private $configLoader;

    /** @var External_Timetable */
    private $timetableHandler;

    /**
     * Test URLS:
     * http://192.168.36.10/picker/datetime                                         <-- HTML for injecting into modal
     * http://192.168.36.10/picker/datetime/date/{date}                             <-- All appointments on a given date
     * http://192.168.36.10/picker/datetime/date/{date}/class/{classcode}           <-- All student for a given class and how many due work items they have that day
     */

    /**
     * Controller constructor.
     *
     * @param UserMapper $userMapper
     * @param TermMapper $termMapper
     * @param UserAccessMapper $userAccessMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     */
    public function __construct(
        UserMapper $userMapper,
        TermMapper $termMapper,
        UserAccessMapper $userAccessMapper,
        AssessmentMapperProxy $assessmentMapperProxy
    ) {
        $this->setUserMapper($userMapper);
        $this->setTermMapper($termMapper);
        $this->setUserAccessMapper($userAccessMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setTimetableHandler(new External_Timetable());
        $this->setConfigLoader(new ConfigLoader());
    }

    /**
     * Return the HTML picker for injecting into modal
     *
     * @param string $type event or duework
     * @param string $element input field that calls a datepicker
     * @param int $id The ID number of the javascript picker
     * @param string $value Default date to display
     */
    public function getPicker($type, $element, $id, $value)
    {
        $timetableVars = [];
        $timetable = $this->getUserTimetable();
        $terms = $this->getTerms();

        try {
            $date = new Alaress_Date_Format($value, 'Y-m-d H:i:s');

        } catch (InvalidArgumentException $e) {
            // if parsing the given date failed we still have to respond with a picker, so falling back
            // to the current date is the only recourse. We do, however, log the failure if possible.
            $date = Alaress_Date_Format::now();

            if (function_exists('newrelic_notice_error')) {
                newrelic_notice_error('Failed to parse date in picker init', $e);
            }
        }

        // Don't bother calculating if the timetable is offline, we'll only display the calendar

        if (is_array($timetable)) {

            $term = $this->termMapper->findForDate($date);

            $timetableVars['term'] = $term->getId();
            $timetableVars['daysUntilDate'] = $term->getDuration($date);
            $timetableVars['isWeekend'] = $date->isWeekend() ? 1 : 0;

            // Get all what we need to build proper timetable per campus

            foreach ($this->getUser()->getCampusCollection() as $campus) {

                $termActualStart = $this->getFirstTimetableDayForTerm($campus, $term);

                // Don't use $campus->getTimetableDay(), it will return number of days set in SB, rather than from External Timetable

                $timetableDay = $timetable[$campus->getId()]['dayCount'];
                $termStartDay = $termActualStart['day'];

                // This is used to get the last term time table day.
                // Alternatively we could have got the last date of term and if it was a weekend we'd gone backwards to get a date for Friday

                $termLastDay = ($term->getDuration() - ($timetableDay + 1 - $termStartDay)) % $timetableDay;
                $selectedDay = $this->getTimetableHandler()->getDayNumber($campus, $date->getUnixTimeStamp());
                $timetableCycles = ceil(($term->getDuration() + $termStartDay - 1) / $timetableDay);

                $timetableVars['byCampus'][$campus->getId()]['id'] = $campus->getId();
                $timetableVars['byCampus'][$campus->getId()]['termStartDay'] = $termStartDay;
                $timetableVars['byCampus'][$campus->getId()]['termLastDay'] = $termLastDay;
                $timetableVars['byCampus'][$campus->getId()]['selectedDay'] = $selectedDay;
                $timetableVars['byCampus'][$campus->getId()]['timetableCycles'] = $timetableCycles;

                // Loop through terms and set cycle start and end dates
                $cycles = [];

                foreach ($terms as $cycleTerm) {

                    $termStartDate = $cycleTerm->getStart();
                    $termEndDate = $cycleTerm->getDisplayFinish();
                    $tempDate = clone $termStartDate;
                    $tempDay = $termStartDay;
                    $cycleDays = $timetableDay;
                    $currentCycle = 1;

                    //$cycles[$cycleTerm->getId()][1]['startDate'] = $tempDate->toIso8601String();

                    while ($tempDate->compareDateOnly($termEndDate) <= 0) {

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
        $timetable = [];
        $campusInfo = [];

        $user = $this->getUser();

        foreach ($user->getCampusCollection() as $campus) {

            $campusTimetable = $this->getTimetableHandler()->getTimetableDefinition(null, $campus);

            if ($campusTimetable) {
                $campusInfo['campusId'] = $campus->getId();
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
     * @return Collection
     */
    public function getTerms()
    {
        $terms = $this->getTermMapper()->findAll(['order' => ['start' => 'desc']]);
        return $terms;
    }

    /**
     * Return a first timetable day for the first monday of a term
     *
     * @param Campus $campus
     * @param Term $term
     * @return array
     */
    public function getFirstTimetableDayForTerm($campus, $term)
    {
        $date = new Alaress_Date_Format($term->getStart());

        // We can't base calculations on a date that falls on a weekend
        // We'll get return a timetable day number for the following Monday

        $termActualStart = [];

        // Skip 1 day if Sunday, 2 days if Saturday

        $dateOffset = !$date->isWeekend() ? 0 : (8 % $date->getWeekDayNumber());

        $timetableDay = $this->getTimetableHandler()->getDayNumber($campus, $date->addDays($dateOffset)->getUnixTimeStamp());
        $termActualStart['date'] = $date;
        $termActualStart['day'] = $timetableDay;

        return $termActualStart;
    }

    /**
     * Return JSON list of all appointments for a given date
     *
     * @param Alaress_Date_Format $date
     */
    public function getByDate(Alaress_Date_Format $date)
    {
        $appointments = [];

        // Get start/end of the date

        $start = $date;
        $end = clone $start;
        $end = $end->addDays(1);

        /** @var Calendar_Api_Factory $factory */
        $factory = $this->getDi()->get('calendarApiFactory');
        $api = $factory->produceForUser($this->getUser());

        $events = $api->getEvents($start->getUnixTimeStamp(), $end->getUnixTimeStamp());

        foreach ($events as $event) {
            $appointments[] = [
                'displayDateTime' => $event->getEventSpan(),
                'title' => $event->getTitle()
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
     * @param Folder $folder
     */
    public function getDueByDate(Alaress_Date_Format $date, $folder)
    {
        $data = [];

        // Only show results to staff

        if ($this->getUser()->getRole()->isStaff()) {

            $start = $date->startOfDay();
            $end = clone $start;
            $end = $end->endOfDay();

            // Get all students for the class or the group

            $externalStudents = $this->getUserAccessMapper()->findUsersInCategory(
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

            // Iterate each student in the class

            $students = [];
            $studentList = $this->getUserMapper()->findByColumn('synergy_id', $externalIds);

            foreach ($studentList as $studentCurrent) {

                // Create a mock user object and find all assessments for this student
                $assessments = $this->getAssessmentMapperProxy()->findAllByStudentBetweenDates($studentCurrent, $start, $end);
                // Count how many assessments are due on the specified date
                $assessmentsDue = count($assessments);
                $assessmentList = [];
                foreach ($assessments as $assessment) {
                    $assessmentList[] = [
                        'id' => $assessment->getId(),
                        'title' => $assessment->getTitle(),
                        'duetime' => $assessment->getDueAt()->getTimeOnly(),
                        'context' => $assessment->getBaseContext()->getFolder()->getName()
                    ];
                }
                if ($assessmentsDue > 0) {
                    $students[] = [
                        'id' => $studentCurrent->getId(),                      // Required for link to student profile
                        'externalId' => $studentCurrent->getExternalId(),      // Required for rendering student photo
                        'firstname' => $studentCurrent->getFirstname(),
                        'lastname' => $studentCurrent->getLastname(),
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

            // Spit this out in a JSON array

            $data = [
                'students' => $students,
                'date' => $date->toIso8601String()
            ];
        }

        // Return results, if any

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => $data
        ]);
    }

    /**
     * Getters/setters below here
     */

    /**
     * @param UserMapper $mapper
     * @return mixed $this
     */
    public function setUserMapper($mapper)
    {
        $this->userMapper = $mapper;
        return $this;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param TermMapper $mapper
     * @return mixed $this
     */
    public function setTermMapper($mapper)
    {
        $this->termMapper = $mapper;
        return $this;
    }

    /**
     * @return TermMapper
     */
    public function getTermMapper()
    {
        return $this->termMapper;
    }

    /**
     * @param UserAccessMapper $mapper
     * @return mixed $this
     */
    public function setUserAccessMapper($mapper)
    {
        $this->userAccessMapper = $mapper;
        return $this;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }

    /**
     * @param AssessmentMapperProxy $mapper
     * @return mixed $this
     */
    public function setAssessmentMapperProxy($mapper)
    {
        $this->assessmentMapperProxy = $mapper;
        return $this;
    }

    /**
     * @return AssessmentMapperProxy
     */
    public function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }

    /**
     * @return External_Timetable
     */
    public function getTimetableHandler()
    {
        return $this->timetableHandler;
    }

    /**
     * @param External_Timetable $timetableHandler
     * @return $this
     */
    public function setTimetableHandler($timetableHandler)
    {
        $this->timetableHandler = $timetableHandler;
        return $this;
    }

    /**
     * @return ConfigLoader
     */
    public function getConfigLoader()
    {
        return $this->configLoader;
    }

    /**
     * @param ConfigLoader $configLoader
     * @return $this
     */
    public function setConfigLoader($configLoader)
    {
        $this->configLoader = $configLoader;
        return $this;
    }
}
