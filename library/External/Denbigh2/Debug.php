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

        $this->setup();

        $this->test();
    }

    /**
     * Set up all the methods to test
     */
    protected function setup() {

        /* Timetable
         */
        $this->addTest(
            'Timetable Definition', 'PeriodTimes',
            array('Period', 'Period Start Time', 'Period End Time'),
            new External_Denbigh2_Api('PeriodTimes', array())
        );

        $this->addTest(
            'Current Day', 'CurrentDay',
            array('Current Day of Cycle'),
            new External_Denbigh2_Api('CurrentDay', array())
        );

        $this->addTest(
            "Student Timetable for {$this->studentId}", 'StudentTimetable',
            array('Day Number', 'Teacher', 'TT Description', 'Period', 'Period Name', 'ClassCode', 'Room', 'TeacherCode'),
            new External_Denbigh2_Api('StudentTimetable', array('Student ID' => $this->studentId))
        );

        $this->addTest(
            "Staff Timetable for {$this->teacherCode}", 'TimetableLessons',
            array('Day', 'Period', 'Description for Staff TT', 'Class', 'Room', 'Teacher Code', 'Teacher'),
            new External_Denbigh2_Api('TimetableLessons',array('Teacher Code' => $this->teacherCode),array('RFMmax' => 0))
        );

        $this->addTest(
            "Subject Name for {$this->classCode}", 'Classes',
            array('Subject Name'),
            new External_Denbigh2_Api('Classes', array('Class' => $this->classCode))
        );

        /* Class
         */
        $this->addTest(
            "List of Students in {$this->classCode}", 'Enrolments',
            array('Surname', 'Preferred Name', 'Academic Year', 'House', 'Medical Alert Label', 'Special Needs', 'Student ID'),
            new External_Denbigh2_Api('Enrolments', array('Class' => $this->classCode))
        );

        $this->addTest(
            "Teacher of {$this->classCode}", 'Classes',
            array('Class', 'Teacher Code'),
            new External_Denbigh2_Api('Classes', array('Class' => $this->classCode))
        );

        /* User
         */
        $this->addTest(
            "Student info for {$this->studentId}", 'Students',
            array('Date of Birth', 'Student ID', 'Given Names', 'Surname', 'House', 'Academic Year', 'Father', 'Father Surname', 'Father Phone', 'Mother', 'Mother Surname', 'Mother Phone', 'Medical Alert Label', 'Special Needs', 'Campus', 'Preferred Name', 'Gender', 'Email Address', 'email Father', 'email Mother'),
            new External_Denbigh2_Api('Students', array('Student ID' => $this->studentId))
        );

        $this->addTest(
            "Contact List for {$this->familyCode}", 'Family',
            array('Father Title', 'Father Preferred Name', 'Father Surname', 'Father email', 'Residential Address Street', 'Residential Address Suburb', 'Residential Address PostCode', 'Residential Address State', 'Father Mobile', 'Father Phone', 'Mother Title', 'Mother Preferred Name', 'Mother Surname', 'Mother Mobile', 'Mother Phone', 'Mother Email'),
            new External_Denbigh2_Api('Family', array('Family Code' => $this->familyCode))
        );

        $this->addTest(
            "Parent info for {$this->familyCode}", 'Family',
            array('Father Community ID', 'Father Title', 'Father Preferred Name', 'Father Surname', 'Father email', 'Father Email Work', 'Father Phone', 'Father Mobile', 'Mother Community ID', 'Mother Title', 'Mother Preferred Name', 'Mother Surname', 'Mother Email', 'Mother Email Work', 'Mother Mobile'),
            new External_Denbigh2_Api('Staff', array('Staff Code' => $this->teacherCode))
        );

        $this->addTest(
            "Staff info for {$this->teacherCode}", 'Staff',
            array('Staff Code', 'Title', 'First Name', 'Surname', 'Email Address', 'Campus', 'Telephone', 'Telephone Extension'),
            new External_Denbigh2_Api('Staff', array('Staff Code' => $this->teacherCode))
        );

        $this->addTest(
            "Subject List for {$this->studentId}", 'Enrolments',
            array('Class', 'Subject Name'),
            new External_Denbigh2_Api('Enrolments', array('Student ID' => $this->studentId))
        );

        $this->addTest(
            "Portrait for {$this->studentId}", 'Photos',
            array('PhotoBase64Encoded'),
            new External_Denbigh2_Api('Photos', array('Community ID' => $this->studentId))
        );

        $this->addTest(
            "Portrait for {$this->teacherCode}", 'Photos',
            array('PhotoBase64Encoded'),
            new External_Denbigh2_Api('Photos', array('z_StaffCode ID' => $this->teacherCode))
        );
    }

    /**
     * Add an api call to test
     *
     * @param $label
     * @param $method
     * @param $expectedKeys
     * @param $api
     */
    private function addTest($label, $method, $expectedKeys, $api) {
        $this->getAllApi()->add(array(

            /* Human friendly label, for display purposes
             */
            'label' => $label,

            /* Method name, for display purposes
             */
            'method' => $method,

            /* These keys need to exist in the result of $api's method call
             */
            'expectedKeys' => $expectedKeys,

            /* The api to execute
             */
            'api' => $api,
        ));
    }

    /**
     * Test all the things
     */
    private function test() {
        $allResults = new Collection();

        foreach ($this->getAllApi() as $currentApi) {

            /* Check for missing fields
             */
            $missingProperties = array();

            $result = array();
            $result['label'] = $currentApi['label'];
            $result['method'] = $currentApi['method'];
            $result['state'] = 0;

            /* Any generic errors: curl errors, 500 Filemaker Error, etc
             */
            if (!$currentApi['api']->hasErrors()) {
                $row = $currentApi['api']->getResult()->data[0];

                foreach ($currentApi['expectedKeys'] as $currentKey) {
                    if (!property_exists($row, $currentKey)) {
                        $missingProperties[] = $currentKey;
                    }
                }

                $result['missingProperties'] = $missingProperties;
                $result['output'] = $row;

                if (!count($missingProperties)) {
                    $result['state'] = 1;

                    $this->success();
                }
            }
            else {
                $result['output'] = $currentApi['api']->getErrors();
            }

            $allResults->add($result);
        }

        /* Output outcome outside
         */
        echo "<style type='text/css'>.success { background-color: #eaffea } .failure { background-color: #ffecec } code { white-space: pre-line; } table { border-collapse: collapse; } td { vertical-align: top; border:1px solid #000; padding: 16px; }</style>";
        echo "<table><thead>";
        echo "<tr>";
        echo "<th>Api Call</th>";
        echo "<th>Missing Properties</th>";
        echo "<th>Api Output</th>";
        echo "</tr></thead>";
        echo "<tbody>";

        foreach($allResults as $currentResult) {

            ob_start();
            echo '<code>';
            print_r($currentResult['output']);
            echo '</code>';
            $shiaLeBuff = ob_get_contents();

            if ($currentResult['state']) {
                $class = 'success';
            }
            else {
                $class = 'failure';
            }

            $row = "<tr class='{$class}'>";
            $row .= "<td style='width:200px'>{$currentResult['label']}<br />";
            $row .= "({$currentResult['method']})</td>";
            $row .= "<td style='width:400px'>";

            if (count($currentResult['missingProperties'])) {
                $row .= '<ul><li>' . implode('</li><li>', $currentResult['missingProperties']) . '</li></ul>';
            }

            $row .= "</td>";
            $row .= "<td style='width:auto;overflow:auto'>";

            $row .= "$shiaLeBuff";

            $row .= "</td>";
            $row .= "</tr>";

            ob_end_clean();

            echo $row;
        }

        echo "</tbody></table>";

        /* Number of things that failed
         */
        if ($this->getSuccessful() < $this->getAllApi()->count()) {
            $failed = $this->getAllApi()->count() - $this->getSuccessful();

            echo "<h1>{$failed} failed, out of {$this->getAllApi()->count()} things tested</h1>";
        }

        die();
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