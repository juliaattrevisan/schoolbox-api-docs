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
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Config;
use Alaress_Date_Format;
use Schoolbox\Role;
use \Schoolbox\Config\Loader as ConfigLoader;

class Controller extends Base
{
    /**
     * Test URLS:
     * http://192.168.36.10/picker/datetime                                         <-- HTML for injecting into modal
     * http://192.168.36.10/picker/datetime/date/{date}                             <-- All appointments on a given date
     * http://192.168.36.10/picker/datetime/date/{date}/class/{classcode}           <-- All student for a given class and how many due work items they have that day
     */

    /**
     * Controller constructor.
     *
     * @param UserMapper            $userMapper
     * @param TermMapper            $termMapper
     * @param FolderMapper          $folderMapper
     * @param UserAccessMapper      $userAccessMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     */
    public function __construct(
        UserMapper $userMapper,
        TermMapper $termMapper,
        FolderMapper $folderMapper,
        UserAccessMapper $userAccessMapper,
        AssessmentMapperProxy $assessmentMapperProxy
    ) {
        $this->setUserMapper($userMapper);
        $this->setTermMapper($termMapper);
        $this->setFolderMapper($folderMapper);
        $this->setUserAccessMapper($userAccessMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setTimetableHandler();
        $this->setConfigureLoader();
    }

    /**
     * Return the HTML picker for injecting into modal
     *
     * @param string $type    event or duework
     * @param string $element input field that calls a datepicker
     * @param int    $id      The ID number of the javascript picker
     * @param string $value   Default date to display
     * @throws \Exception
     */
    public function getPicker($type, $element, $id, $value) {
        $timetable = $this->getUserTimetable();
        $terms = $this->getTerms();
        try {
            $date = new Alaress_Date_Format($value, 'Y-m-d H:i:s');
        }
        catch (\Exception $e) {
            // TODO handle the error properly
        }

        // Don't bother calculating if the timetable is offline, we'll only display the calendar
        if(is_array($timetable)) {

            $term = $this->termMapper->findForDate($date);
            $timetableVars = Array();
            $timetableVars['term'] = $term->getId();
            $timetableVars['daysUntilDate'] = $term->getDuration($date);
            $timetableVars['isWeekend'] = 0;
            if($date->isWeekend()) {
                $timetableVars['isWeekend'] = 1;
            }
            // Get all what we need to build proper timetable per campus
            foreach ($this->getUser()->getCampusCollection() as $campus) {
                $termActualStart = $this->getFirstTimetableDayForTerm($campus, $term);

                // Don't use $campus->getTimetableDay(), it will return number of days set in SB, rather than from External Timetable
                $timetableDay = $timetable[$campus->getId()]['dayCount'];
                $termStartDay = $termActualStart['day'];
                // This is used to get the last term time table day.
                // Alternatively we could have got the last date of term and if it was a weekend we'd gone backwards to get a date for Friday
                $termLastDay = ($term->getDuration()-($timetableDay+1-$termStartDay))%$timetableDay;
                $selectedDay = $this->getTimetableHandler()->getDayNumber($campus, $date->getUnixTimeStamp());
                $timetableCycles = ceil(($term->getDuration()+$termStartDay-1)/$timetableDay);
                $selectedCycle = ceil(($term->getDuration($date)+$termStartDay-1)/$timetableDay);

                $timetableVars['byCampus'][$campus->getId()]['id']              = $campus->getId();
                $timetableVars['byCampus'][$campus->getId()]['termStartDay']    = $termStartDay;
                $timetableVars['byCampus'][$campus->getId()]['termLastDay']     = $termLastDay;
                $timetableVars['byCampus'][$campus->getId()]['selectedDay']     = $selectedDay;
                $timetableVars['byCampus'][$campus->getId()]['timetableCycles'] = $timetableCycles;
                $timetableVars['byCampus'][$campus->getId()]['selectedCycle']   = $selectedCycle;
            }
        }

        $displayFormatDate = strlen(Config::get('date_local')) > 0 ? Config::get('date_local'): 'd/m/Y';
        $displayFormatTime = strlen(Config::get('date_time')) > 0 ? Config::get('date_time'): 'h:iK';

        $this->getResponse()->content->set(
            array(
                'template' => '@pickerDatetime/picker.twig',
                'format' => 'nochrome',
                'data' => [
                    'id' => $id,
                    //Pass unix timestamp to flatpicker, it misinterprets day and month in d/m/Y format
                    'value' => $date->toIso8601String(),
                    'element' => $element,
                    'type' => $type,
                    'displayFormat' => $this->convertFormat($displayFormatDate).' '.$this->convertFormat($displayFormatTime),
                    'displayFormatDate' => $this->convertFormat($displayFormatDate),
                    'weekStartDay' => $this->getConfigureLoader()->get('calendar_start_day'),
                    'terms' => $terms,
                    // If the connector is offline, pass FALSE
                    'timetable' => $timetable,
                    'timetableVars' => $timetableVars
                ]
            )
        );
    }

    /**
     * Converts schoolbox config date and time formats info flatpickr friendly
     *
     * @param string $format
     * @return string
     */
    public function convertFormat($format) {
        return strtr(
            $format,
            array(
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
            )
        );
    }

    /**
     * Return an array of all timetables and campus data
     *
     * @return array
     */
    public function getUserTimetable() {
        $user = $this->getUser();

        $timetable = array();
        $campusInfo = array();

        foreach ($user->getCampusCollection() as $campus) {
            $campusTimetable = $this->getTimetableHandler()->getTimetableDefinition(null, $campus);

            if($campusTimetable) {
                $campusInfo['campusId']     = $campus->getId();
                $campusInfo['campusName']   = $campus->getName();
                $campusInfo['campusCode']   = $campus->getExtTimetableCode();

                //Return campus and it's timetable in one array
                $timetable[$campus->getId()] = array_merge($campusInfo, $campusTimetable);
            }

            else {
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
    public function getTerms() {
        $terms = $this->getTermMapper()->findAll(['order' => ['start' => 'desc']]);
        return $terms;
    }

    /**
     * Return a first timetable day for the first monday of a term
     *
     * @param $campus
     * @param $term
     * @return array
     */
    public function  getFirstTimetableDayForTerm($campus, $term) {
        $date = new Alaress_Date_Format($term->getStart());

        // We can't base calculations on a date that falls on a weekend
        // We'll get return a timetable day number for the following Monday
        $termActualStart = Array();
        $dateOffset = 0;
        if($date->isWeekend()) {
            // It is Sunday
            $dateOffset = 1;
            // Or maybe Saturday
            if($date->getWeekDayNumber() == 6) {
                $dateOffset = 2;
            }
        }
        $timetableDay = $this->getTimetableHandler()->getDayNumber($campus, $date->addDays($dateOffset)->getUnixTimeStamp());
        $termActualStart['date'] = $date;
        $termActualStart['day'] = $timetableDay;

        return $termActualStart;
    }

    /**
     * Call getByTimetableDay to return events for timetable day
     *
     * @param int $campusId
     * @param int $termId
     * @param int $cycle timetable cycle or week (calculated from the beginning of the term)
     * @param int $day timetable day number
     * @return Collection
     */
    public function getEventsByTimetableDay ($campusId, $termId, $cycle, $day) {
        return $this->getByTimetableDay($campusId, $termId, $cycle, $day);
    }

    /**
     * Return a list of events or assessments due for a given timetable cycle and day (e.g. Day 5 of 2nd cycle)
     *
     * @param int $campusId
     * @param int $termId
     * @param int $cycle timetable cycle or week (calculated from the beginning of the term)
     * @param int $day timetable day number
     * @param string $folder class code
     * @param boolean $dateonly just returns date
     * @return Collection|json
     */
    public function getByTimetableDay($campusId, $termId, $cycle, $day, $folder = 0, $dateOnly = false) {
        $user = $this->getUser();
        //Get Campus object
        $campus = $user->getCampusCollection()->getBy('id', $campusId);

        //Get particular term
        $term = $this->getTerms()->getBy('id', $termId);

        $date = $this->getDateForTimetableDay($campus, $term, $cycle, $day);

        //Return duework
        if($folder > 0){
            return $this->getDueByDate($date, $folder);
        }
        if($dateOnly == true) {
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data' => [
                        'date' => $date->toIso8601String()
                    ]
                )
            );
        }
        //return events
        return $this->getByDate($date);

    }


    /**
     * Get a date for a timetable day
     *
     * @param $campusId
     * @param $termId
     * @param $cycle
     * @param $day
     *
     * @return Collection|json
     */
    public function getTimetableDate($campusId, $termId, $cycle, $day) {

        return $this->getByTimetableDay($campusId, $termId, $cycle, $day, '', true);
    }

    /**
     * Converting timetable cycle and day into a calendar date
     *
     * @param object $campus
     * @param object $term
     * @param int $cycle
     * @param int $day
     * @return Alaress_Date_Format
     */
    public function getDateForTimetableDay($campus, $term, $cycle, $day) {

        $campusTimetable = $this->getTimetableHandler()->getTimetableDefinition(null, $campus);
        $daysInCycle = $campusTimetable['dayCount'];

        $termActualStart = $this->getFirstTimetableDayForTerm($campus, $term);

        // Knowing the term start date, it's timetable day and timetable cycle length, figure out which date is the given timetable day
        $shiftTimetableDays = $daysInCycle*($cycle-1)+$day-$termActualStart['day'];
        $date = $termActualStart['date']->addWeekdays($shiftTimetableDays);
        return $date;
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

        $factory = $this->getDi()->get('calendarApiFactory');
        $api = $factory->produceForUser($this->getUser());

        try {
            $events = $api->getEvents($start->getUnixTimeStamp(), $end->getUnixTimeStamp());
            foreach ($events as $event) {
                $appointments[] = [
                    'displayDateTime' => $event->getEventSpan(),
                    'title' => $event->getTitle()
                ];
            }

        } catch (\Exception $e) {
            $this->getResponse()->status->setCode(404);
        }

        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data' => [
                    'appointments' => $appointments,
                    'date' => $date->toIso8601String()
                ]
            )
        );
    }

    /**
     * Return JSON list of students and how much due work that have for a given date and class code(s)
     *
     * @param Alaress_Date_Format $date (Date we are interested in)
     * @param string $classCode (CSV list of class codes)
     */
    public function getDueByDate(Alaress_Date_Format $date, $folder)
    {

        //Don't show other students' due work to anyone but staff
        if(!$this->getUser()->getRole()->isStaff()) {
            $this->getResponse()->content->set(
                array(
                    'format' => 'json',
                    'data' => []
                )
            );
            return '';
        }

        $students = [];

        // Get all students for the class or the group

        $folderModel = $this->getFolderMapper()->findById($folder);
        $externalStudents = $this->getUserAccessMapper()->findUsersInCategory(
            $folderModel,
            Role::STUDENT,
            UserAccess::ACCESS_READ,
            true
        );

        // Get a lookup array of external IDs for the students in this class

        $externalIds = [];

        foreach ($externalStudents as $studentCurrent) {
            $externalIds[] = $studentCurrent->getExternalId();
        }

        $studentList = $this->getUserMapper()->findByColumn('synergy_id', $externalIds);
        // Iterate each student in the class

        $start = $date->startOfDay();
        $end = clone $start;
        $end = $end->endOfDay();

        foreach ($studentList as $studentCurrent) {

            // Create a mock user object and find all assessments for this student
            $assessments = $this->getAssessmentMapperProxy()->findAllByStudentBetweenDates($studentCurrent, $start, $end);
            // Count how many assessments are due on the specified date
            $assessmentsDue = count($assessments);
            $assessmentList = Array();
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

        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data' => [
                    'students' => $students,
                    'date' => $date->toIso8601String()
                ]
            )
        );
    }

    /**
     * Getters/setters below here
     */

    /** @var UserMapper */
    private $userMapper;

    /**
     * @param UserMapper $mapper
     * @return mixed $this
     */
    public function setUserMapper($mapper) {
        $this->userMapper = $mapper;
        return $this;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper() {
        return $this->userMapper;
    }

    /** @var TermMapper */
    private $termMapper;

    /**
     * @param TermMapper $mapper
     * @return mixed $this
     */
    public function setTermMapper($mapper) {
        $this->termMapper = $mapper;
        return $this;
    }

    /**
     * @return TermMapper
     */
    public function getTermMapper() {
        return $this->termMapper;
    }

    /** @var FolderMapper */
    private $folderMapper;

    /**
     * @param FolderMapper $mapper
     * @return mixed $this
     */
    public function setFolderMapper($mapper) {
        $this->folderMapper = $mapper;
        return $this;
    }

    /**
     * @return FolderMapper
     */
    public function getFolderMapper() {
        return $this->folderMapper;

    }

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /**
     * @param UserAccessMapper $mapper
     * @return mixed $this
     */
    public function setUserAccessMapper($mapper) {
        $this->userAccessMapper = $mapper;
        return $this;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper() {
        return $this->userAccessMapper;
    }

        /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

    /**
     * @param AssessmentMapperProxy $mapper
     * @return mixed $this
     */
    public function setAssessmentMapperProxy($mapper) {
        $this->assessmentMapperProxy = $mapper;
        return $this;
    }

    /**
     * @return AssessmentMapperProxy
     */
    public function getAssessmentMapperProxy() {
        return $this->assessmentMapperProxy;
    }

    /**
     * @return mixed $this
     */
    public function setTimetableHandler() {
        $this->timetableHandler = new \External_Timetable;
        return $this;
    }

    /**
     * @return TimetableHandler
     */
    public function getTimetableHandler() {
        return $this->timetableHandler;
    }

    /** @var ConfigureLoader */
    private $configureLoader;

    /**
     * @return mixed $this
     */
    public function setConfigureLoader() {
        $this->configureLoader = new ConfigLoader();
        return $this;
    }

    /**
     * @return ConfigureLoader
     */
    public function getConfigureLoader() {
        return $this->configureLoader;
    }

}
