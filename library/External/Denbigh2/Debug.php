<?php
/**
 * Debug
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Alaress_Probe as Probe;
use Schoolbox\Collection;

class External_Denbigh2_Debug {

    /**
     * A collection of api methods to test
     *
     * @var Collection
     */
    private $allApi = null;

    /**
     * Number of successful tests
     *
     * @var int
     */
    private $successful = 0;

    /**
     * External ID of a student
     *
     * @var int
     */
    private $studentId = 60949;

    /**
     * Family code for $studentId
     *
     * @var string
     */
    private $familyCode = 'ABE002';

    /**
     * Teacher Code
     *
     * @var string
     */
    private $teacherCode = 'Akka23';

    /**
     * Class Code
     *
     * @var string
     */
    private $classCode = '11IBISL_2';


    /**
     * Constructor
     */
    public function __construct() {
        $this->setAllApi(new Collection());

        /* Timetable
         */
        $this->addTest('Timetable Definition', 'PeriodTimes', new External_Denbigh2_Api('PeriodTimes', array()));
        $this->addTest('Current Day', 'CurrentDay', new External_Denbigh2_Api('CurrentDay', array()));
        $this->addTest("Student Timetable for {$this->studentId}", 'StudentTimetable', new External_Denbigh2_Api('StudentTimetable', array('Student ID' => $this->studentId)));
        $this->addTest("Staff Timetable for {$this->teacherCode}", 'TimetableLessons', new External_Denbigh2_Api('TimetableLessons',array('Teacher Code' => $this->teacherCode),array('RFMmax' => 0)));
        $this->addTest("Subject Name for {$this->classCode}", 'Classes', new External_Denbigh2_Api('Classes', array('Class' => $this->classCode)));

        /* Class
         */
        $this->addTest("List of Students in {$this->classCode}", 'Enrolments', new External_Denbigh2_Api('Enrolments', array('Class' => $this->classCode)));
        $this->addTest("Teacher of {$this->classCode}", 'Classes', new External_Denbigh2_Api('Classes', array('Class' => $this->classCode)));

        /* User
         */
        $this->addTest("Student info for {$this->studentId}", 'Students', new External_Denbigh2_Api('Students', array('Student ID' => $this->studentId)));
        $this->addTest("Contact List for {$this->familyCode}", 'Family', new External_Denbigh2_Api('Family', array('Family Code' => $this->familyCode)));
        $this->addTest("Staff info for {$this->teacherCode}", 'Staff', new External_Denbigh2_Api('Staff', array('Staff Code' => $this->teacherCode)));
        $this->addTest("Subject List for {$this->studentId}", 'Enrolments', $api = new External_Denbigh2_Api('Enrolments', array('Student ID' => $this->studentId)));

        $this->test();
    }

    /**
     * Add an api call to test
     *
     * @param $label
     * @param $method
     * @param $api
     */
    private function addTest($label, $method, $api) {
        $this->getAllApi()->add(array(
            'label' => $label,
            'method' => $method,
            'api' => $api,
        ));
    }

    /**
     * Test all the things
     */
    private function test() {
        foreach ($this->getAllApi() as $currentApi) {
            echo "<strong>{$currentApi['label']} <em>{$currentApi['method']}</em></strong>";

            if (!$currentApi['api']->hasErrors()) {
                new Probe($currentApi['api']->getResult()->data[0]);
                $this->success();
            }
            else {
                new Probe($currentApi['api']->getErrors());
            }

            echo '<hr>';
        }

        /* Number of things that failed
         */
        if ($this->getSuccessful() < $this->getAllApi()->count()) {
            $failed = $this->getAllApi()->count() - $this->getSuccessful();

            echo "{$failed} failed.";
        }
    }

    /* Getters/Setters
     */

    /**
     * Success counter
     */
    private function success() {
        $this->successful++;
    }

    /**
     * Set successful
     *
     * @param int $successful
     */
    private function setSuccessful($successful)
    {
        $this->successful = $successful;
    }

    /**
     * Get successful
     *
     * @return int
     */
    private function getSuccessful()
    {
        return $this->successful;
    }

    /**
     * Set all api
     *
     * @param \Schoolbox\Collection $allApi
     */
    private function setAllApi($allApi)
    {
        $this->allApi = $allApi;
    }

    /**
     * Get all api
     *
     * @return \Schoolbox\Collection
     */
    private function getAllApi()
    {
        return $this->allApi;
    }
}