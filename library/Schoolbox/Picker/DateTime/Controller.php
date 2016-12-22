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
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use \Calendar_Layout_Full_Ajax_JSON;
use \Group;
use \External_Timetable;
use \Schoolbox\CampusMapper;
use \External_Synergetic_Timetable;

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

    }


    /**
     * Return the HTML picker for injecting into modal
     * @param int $id The ID number of the javascript picker
     * @param string $value Default date to display
     */
    public function getPicker($id, $display, $database, $value) {
        $timetable = $this->getTimetable();
        $terms = $this->getTerms();

//        new \Alaress_Probe($timetable);

        $this->getResponse()->content->set(
            array(
                'template' => '@pickerDatetime/picker.twig',
                'format' => 'nochrome',
                'data' => [
                    'id' => $id,
                    'value' => $value,
                    'displayFormat' => urldecode($display),
                    'databaseFormat' => urldecode($database),
                    'timetable' => $timetable,
                    'terms' => $terms
                ]
            )
        );
    }

    /**
     * Return an array of all timetables and campus data
     */
    public function getTimetable() {
        $user = $this->getUser();
        $timetableHandler = new External_Timetable;
        $timetable = array();
        $campusInfo = array();

        foreach ($user->getCampusCollection() as $campus) {
            $campusTimetable = $timetableHandler->getTimetableDefinition(null, $campus);
            $campusInfo['campusId'] = $campus->getId();
            $campusInfo['campusName'] = $campus->getName();
            $campusInfo['campusCode'] = $campus->getExtTimetableCode();

            //Return campus and it's timetable in one array
            $timetable[$campus->getId()] = array_merge($campusInfo, $campusTimetable);
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
     * @param object $campus
     * @param string $termStartDate
     */
    public function  getFirstDayOfTerm($campus, $termStartDate) {

        $timetable = new \External_Synergetic_Timetable;

        $termStartTimestamp = new \Alaress_Date_Format($termStartDate, 'd/m/Y h:iA');

        $date =  $timetable->getDayNumber ($campus, $termStartTimestamp->getUnixTimeStamp());

        return $date;
    }

    /**
     * Return a a list of events for a given timetable cycle and day (e.g. Day 5 of 2nd cycle)
     */
    public function getByTimetableDay($campusId, $termId, $cycle, $day) {

        $user = $this->getUser();

        //Get Campus object
        $campus = $user->getCampusCollection()->getBy('id', $campusId);

        //Get particular term
        $term = $this->getTerms()->getBy('id', $termId);

        //When this term starts?
        $termStartDate = $term->getStart()->getLocal();

        //What's it's first timetabe day? (TODO: this is using the current timetable, needs to use a proper one for the term)
        $termFirstDay = $this->getFirstDayOfTerm($campus, $termStartDate);

        $timetableHandler = new External_Timetable;
        $campusTimetable = $timetableHandler->getTimetableDefinition(null, $campus);
        $daysInCycle = $campusTimetable['dayCount'];

        $date = $this->getDateForTimetableDay($termStartDate, $termFirstDay, $daysInCycle, $cycle, $day);
        return $this->getByDate($date);

    }

    /**
     * Converting timetable cycle and day into a calendar date
     */
    public function getDateForTimetableDay($termStartDate, $termFirstDay, $daysInCycle, $cycle, $day) {

        $termStartDate = new \Alaress_Date_Format($termStartDate, 'd/m/Y h:iA');

        //Knowing the term start date, it's timetable day and timetable cycle length, figure out which date is the given timetable day
        $shiftTimetableDays = $daysInCycle*($cycle-1)+$day-$termFirstDay;
        $date = $termStartDate->addWeekdays($shiftTimetableDays)->getDatabase();

        return $date;
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

            $timezoneUTC = new \DateTimeZone('UTC');

            $start = \Alaress_Date_Format::newDatabaseDate($date, $timezoneUTC);
            $end = \Alaress_Date_Format::newDatabaseDate($date, $timezoneUTC)->addHours(23)->addMinutes(59)->addSeconds(59);
            $user = $this->getUser();
            $api = new  \Calendar_Api_EventType_Event_Schoolbox_Default($user, array());
            $events = $api->getEvents($start->getUnixTimeStamp(), $end->getUnixTimeStamp());
            foreach ($events as $event) {
                $appointments[] = [
                    'startDateTime' => $event->getStartTime()->getDatabase(),
                    'endDateTime' => $event->getEndTime()->getDatabase(),
                    'title' => $event->getTitle()
                ];
            }

            // $appointments = $this->loadDummyAppointments();

        } catch (\Exception $e) {
            var_dump($e->getMessage());
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
    public function getByClassCode($date, $classCode)
    {
        // Test URL with assignments due:
        // http://192.168.36.10/picker/datetime/date/01-07-2018/class/CH123

        // This will be our return array

        $students = [];

        // Get all students in the class code

        $externalClassHandler = new \External_Class();
        $externalStudents = $externalClassHandler->getStudents($classCode);

        // Get start/end of the date

        $timezoneUTC = new \DateTimeZone('UTC');

        $start = \Alaress_Date_Format::newUrlDate($date, $timezoneUTC);
        $end = \Alaress_Date_Format::newUrlDate($date, $timezoneUTC)->addHours(23)->addMinutes(59)->addSeconds(59);

        // Find out the term this date falls in (needed to locate assessments)

        $term = $this->getTermMapper()->findForDate($start);

        // Get a lookup array of external IDs for the students in this class

        $externalIds = [];

        foreach ($externalStudents as $studentCurrent) {
            $externalIds[] = $studentCurrent['synergy_id'];
        }

        $userIdLookup = $this->getUserMapper()->findCheaplyByExternalIds($externalIds);

        // Iterate each student in the class

        foreach ($externalStudents as $studentCurrent) {

            // Create a mock user object and find all assessments for this student

            $student = new User();
            $student->setId($userIdLookup[$studentCurrent['synergy_id']]);

            $assessments = $this->getAssessmentMapperProxy()->findAllByStudent($student, $term);

            // TODO: AssessmentMapperProxy needs a function to find all by due date rather than term

            // Count how many assessments are due on the specified date

            $assessmentsDue = 0;

            foreach ($assessments as $assessment) {
                if ($assessment->getDueAt()->between($start, $end)) {
                    $assessmentsDue++;
                }
            }

            if ($assessmentsDue > 0) {
                $students[] = [
                    'id' => $student->getId(),                          // Required for link to student profile
                    'externalId' => $studentCurrent['synergy_id'],      // Required for rendering student photo
                    'firstname' => $studentCurrent['firstname'],
                    'lastname' => $studentCurrent['lastname'],
                    'assessments' => $assessmentsDue                    // Number of assessments they have due on the date
                ];
            }
        }

        // Sort by the number of assignments due

        $assessmentsDue = [];

        foreach ($students as $key => $row) {
            $assessmentsDue[$key] = $row['assessments'];
        }

        array_multisort($assessmentsDue, SORT_DESC, $students);

        // Spit this out in a JSON array

        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data' => [
                    'students' => $students
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
