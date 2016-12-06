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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Test URLS:
     * http://192.168.36.10/picker/datetime                                         <-- HTML for injecting into modal
     * http://192.168.36.10/picker/datetime/date/{date}                             <-- All appointments on a given date
     * http://192.168.36.10/picker/datetime/date/{date}/class/{classcode}           <-- All appointments by student for a given date/class
     */

    /**
     * Controller constructor.
     * @param UserMapper $userMapper
     */
    public function __construct(UserMapper $userMapper)
    {
        $this->setUserMapper($userMapper);
    }

    /**
     * Return the HTML picker for injecting into modal
     */
    public function getPicker() {
        $this->getResponse()->content->set(
            array(
                'template' => '@pickerDatetime/picker.twig',
                'format' => 'nochrome',
                'data' => [
                ]
            )
        );
    }

    /**
     * Return JSON list of appointments for a given date
     * @param string $date
     */
    public function getByDate($date)
    {
        $appointments = [];

        try {
            $timezoneUTC = new \DateTimeZone('UTC');

            $start = \Alaress_Date_Format::newUrlDate($date, $timezoneUTC);
            $end = \Alaress_Date_Format::newUrlDate($date, $timezoneUTC)->addHours(23)->addMinutes(59)->addSeconds(59);

            $api = new \Calendar_Api_EventType_Event_Schoolbox_All();
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
        }

        $this->getResponse()->content->set(
            array(
                'format' => 'json',
                'data' => [
                    'appointments' => $appointments
                ]
            )
        );
    }

    /**
     * Return JSON list of students and their appointments for a given date and class code
     * @param string $date
     * @param string $classCode
     */
    public function getByClassCode($date, $classCode)
    {
        $students = $this->loadDummyStudents();

        foreach ($students as &$studentCurrent) {
            // Shove some random appointments on to each student
            $studentCurrent['appointments'] = $this->loadDummyAppointments();
        }

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
