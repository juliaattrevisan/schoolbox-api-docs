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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \External_Timetable;
use \Config;
use Alaress_Date_Format;
use Schoolbox\Role;

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
     * @param UserMapper $userMapper
     * @param TermMapper $termMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     */
    public function __construct(
        UserMapper $userMapper,
        TermMapper $termMapper,
        AssessmentMapperProxy $assessmentMapperProxy
    ) {
        $this->setUserMapper($userMapper);
        $this->setTermMapper($termMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setTimetableHandler();

    }


    /**
     * Return the HTML picker for injecting into modal
     * @param int $id The ID number of the javascript picker
     * @param string $value Default date to display
     */
    public function getPicker($type, $element, $id, $value) {
        $timetable = $this->getTimetable();
        $terms = $this->getTerms();
        try {
            $dateObject = new Alaress_Date_Format($value, array('d/m/Y g:ia', 'd/m/Y'));
        }
        catch (\Exception $e) {
            var_dump($e->getMessage());
        }

        $timetableDayNumber = false;
        // Don't bother calculating the day, if the timetable is offline, we'll only display the calendar
        if(is_array($timetable)) {
            $timetableVars = $this->getTimetableVars($dateObject);
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
                    'value' => $dateObject->getJSDate(),
                    'element' => $element,
                    'type' => $type,
                    'displayFormat' => $this->convertFormat($displayFormatDate).' '.$this->convertFormat($displayFormatTime),
                    'displayFormatDate' => $this->convertFormat($displayFormatDate),
                    'weekStartDay' => Config::get('calendar_start_day'),
                    'terms' => $terms,
                    // If the connector is offline, pass FALSE
                    'timetable' => $timetable,
                    'timetableDay' => $timetableVars
                ]
            )
        );
    }

    /**
     * Converts schoolbox config date and time formats info flatpickr friendly
     * @param $format
     */
    public function convertFormat($format){
        $formatMap = array(
            '/S/' => '', // not supported
            '/h/' => 'h', // 01-12 not supported, use 1-12 hours
            '/g/' => 'h', // 1-12 hours
            '/a/' => 'K', // no lowercase am/pm supported unfortunately
            '/A/' => 'K', // AM/PM
            '/G/' => 'H', // 00 - 23 hours
            '/s/' => 'S', // 00 - 59 seconds
        );
        return preg_replace(array_keys($formatMap), array_values($formatMap), trim($format));
    }

    /**
     * Return an array of all timetables and campus data
     */
    public function getTimetable() {
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
     */
    public function getTerms() {
        $terms = $this->getTermMapper()->findAll(['order' => ['start' => 'desc']]);
        return $terms;
    }

    /**
     * Return a first timetable day for a term
     * @param $campus
     * @param $term
     * @return Alaress_Date_Format $date
     */
    public function  getFirstTimetableDayForTerm($campus, $term) {
        $date = new Alaress_Date_Format($term->getStart());

        // We can't base calculations on a date that falls on a weekend
        // If it is Saturday, we'll get a timetable day number for the following Monday and take away 2 days for the term start day
        // If it is Sunday, we'll take away 1 day
        if($date->isWeekend()) {
            $weekDay = $date->getWeekDayNumber();
            // It is Saturday
            if($weekDay == 6) {
                $timetableDay =  $this->getTimetableHandler()->getDayNumber($campus, $date->addDays(2)->getUnixTimeStamp());
                return $timetableDay - 2;
            }
            else {
                $timetableDay = $this->getTimetableHandler()->getDayNumber($campus, $date->addDays(1)->getUnixTimeStamp());
                return $timetableDay - 1;
            }
        }
        return $timetableDay = $this->getTimetableHandler()->getDayNumber($campus, $date->getUnixTimeStamp());
    }

    /**
     * Call getByTimetableDay to return events for timetable day
     */
    public function getEventsByTimetableDay ($campusId, $termId, $cycle, $day) {
        return $this->getByTimetableDay($campusId, $termId, $cycle, $day);
    }

    /**
     * Return a list of events or assessments due for a given timetable cycle and day (e.g. Day 5 of 2nd cycle)
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
                        'date' => $date
                    ]
                )
            );
        }
        //return events
        return $this->getByDate($date);

    }

    public function getTimetableDate($campusId, $termId, $cycle, $day) {

        return $this->getByTimetableDay($campusId, $termId, $cycle, $day, '', true);
    }

    /**
     * Converting timetable cycle and day into a calendar date
     */
    public function getDateForTimetableDay($campus, $term, $cycle, $day) {

        $campusTimetable = $this->getTimetableHandler()->getTimetableDefinition(null, $campus);
        $daysInCycle = $campusTimetable['dayCount'];

        $termStartDate = $term->getStart();

        $termFirstDay = $this->getFirstTimetableDayForTerm($campus, $term);

        //Knowing the term start date, it's timetable day and timetable cycle length, figure out which date is the given timetable day
        $shiftTimetableDays = $daysInCycle*($cycle-1)+$day-$termFirstDay;
        $date = $termStartDate->addWeekdays($shiftTimetableDays)->getLocalShort();
        return $date;
    }

    public function getTimetableVars(\Alaress_Date_Format $date) {

        //Get term for given date
        $term = $this->getTermMapper()->findForDate($date);

        $timetableDay = Array();
        $timetableDay['term'] = $term->getId();
        $timetableDay['daysUntilDate'] = $term->getDuration($date);
        $timetableDay['isWeekend'] = 0;
        if($date->isWeekend()) {
            $timetableDay['isWeekend'] = 1;
        }

        return $timetableDay;
    }

    /**
     * Return JSON list of all appointments for a given date
     * @param string $date
     */
    public function getByDate($date)
    {
        $appointments = [];
        try {

            // Get start/end of the date
            $start = \Alaress_Date_Format::newLocalShort($date);
            $end = \Alaress_Date_Format::newLocalShort($date)->addDays(1);

            $factory = $this->getDi()->get('calendarApiFactory');
            $api = $factory->produceForUser($this->getUser());
            $events = $api->getEvents($start->getUnixTimeStamp(), $end->getUnixTimeStamp());
            foreach ($events as $event) {
                if($event->getAllDay()) {
                    $displayDateTime = 'All day event';
                }
                else {
                    $displayDateTime = $event->getStartTime()->getDateOnly() != $event->getEndTime()->getDateOnly() ?
                        $event->getStartTime() . ' - ' . $event->getEndTime() :
                        $event->getStartTime()->getTimeOnly() . ' - ' . $event->getEndTime()->getTimeOnly();
                }
                $appointments[] = [
                    'displayDateTime' => $displayDateTime,
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
                    'date' => $date
                ]
            )
        );
    }

    /**
     * Return JSON list of students and how much due work that have for a given date and class code(s)
     * @param string $date (Date we are interested in)
     * @param string $classCode (CSV list of class codes)
     */
    public function getDueByDate($date, $folder)
    {
        // Test URL with assignments due:
        // http://192.168.36.10/picker/datetime/date/01-07-2018/class/CH123

        // This will be our return array

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

        $folderModel = FolderMapper::getInstance()->findById($folder);
        $externalStudents = UserAccessMapper::getInstance()->findUsersInCategory(
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

        $from = new Alaress_Date_Format($date, 'd/m/Y');
        $to = new Alaress_Date_Format($date, 'd/m/Y');

        foreach ($studentList as $studentCurrent) {

            // Create a mock user object and find all assessments for this student
            $assessments = $this->getAssessmentMapperProxy()->findAllByStudentBetweenDates($studentCurrent, $from->startOfDay(), $to->endOfDay());

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
                    'date' => $date
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


    // TODO: Remove dummy routines below --------------------------------------------------------------------------------------------

    /**
     * Create an array of dummy appointments
     * @return array
     */
    private function loadDummyAppointments() {
        $appointments = [];

        $titles = [
            'Bavte sa, špeciálne očkovacie sérum môže suspendovať z',
            'M. Nabrúsení punkeri to naozaj pravda',
            'Лорем ипсум долор сит амет',
            'Ет меи агам ехпетенда цонсеяуунтур',
            'Регионе сплендиде яуо ет',
            '多ゅ豊況ス枠背覇ヌロフ川答まきふら稿状千大エソ不',
            '災類おも国提トハノモ載1走ホルミ摩3入版クトホ街10軟',
            'Λορεμ ιπσθμ δολορ σιτ αμετ',
            'θτ σεα τατιον νεcεσσιτατιβθσ',
            'Νισλ εσσεντ ιν μελ',
            '面経女会真車問方提関相巨井数',
            '海部一目選昨申海落度載音方',
            'Znat vrem zubis vi gde, gaz imajsx pomocijsx to',
            'Hémecht prächteg hannendrun mir un',
            'Lorem ipsum dolor sit amet',
            'Audiam persius an sed'
        ];

        for ($i=0; $i<rand(3,15); $i++) {
            $appointments[] = [
                'startDateTime' => \Alaress_Date_Format::now()->addHours(-1 * rand(1, 12))->addMinutes(rand(0,45))->toIso8601String(),
                'endDateTime' => \Alaress_Date_Format::now()->addHours(rand(1,12))->addMinutes(rand(0,45))->toIso8601String(),
                'title' => $titles[rand(0,count($titles)-1)]
            ];
        }

        return $appointments;
    }

    /**
     * Create an array of randomly selected students
     * @return array
     */
    private function loadDummyStudents() {
        $count = rand(7,15);

        $students = [];
        $studentsAll = $this->getUserMapper()->findByColumn('group_id', 5);

        /** @var User $student */
        foreach ($studentsAll as $student) {
            if (rand(1,5) == 1) {
                $students[] = [
                    'firstname' => $student->getFirstname(),
                    'lastname' => $student->getLastname(),
                    'externalId' => $student->getExternalId(),
                    'id' => $student->getId()
                ];
            }

            if (count($students) == $count) {
                break;
            }
        }

        return $students;
    }
}

/*
 * This code will self-destruct in 5 seconds...
 *
          ,--.!,
       __/   -*-
     ,d08b.  '|`
     0088MM
     `9MMP'
*/
