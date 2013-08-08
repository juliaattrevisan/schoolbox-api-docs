<?php
/**
 *
 * CLI-facing object to encapsulate all strategies for syncronisation to minimise code duplication across schools
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Facade {

    private $verbose;
    private $perform;
    private $school;
    private $strats;
    private $db;
    private $stratBase = 'Synchronise_%s_%s_Strategy_%s';
    private $facadeBase = 'Synchronise_%s_Main';


    public function __construct($school, $verbose = true, $perform = false, $db = '') {
        $this->school = $school;
        $this->verbose = $verbose;
        $this->perform = $perform;
        $this->db = ($db) ? $db : ucfirst(strtolower(Config::get('external_type')));

        // check for remapping the Schoolbox db type to be External (which refers to CSV digestion)
        if($this->db == 'Schoolbox') {
            $this->db = 'External';
        }
    }

    /**
     * @param array<string>
     */
    public function setStrategies($strats) {
        if (is_array($strats)) {
            $this->strats = $strats;
        }
    }

    public function execute() {
        // Do each strat
        $strats = array();
        $facade = sprintf($this->facadeBase, $this->db);

        foreach($this->strats as $currentStrategy) {
            // double-nested polymorphism anyone? This may need refactoring.
            // this will also call setup() in some cases, which is reserved for destructive changes
            $strats[$currentStrategy] = new $facade(sprintf($this->stratBase, $this->db, $this->school, $currentStrategy));
        }

        foreach ($this->strats as $currentStrategy) {
            $sync = $strats[$currentStrategy];

            // Do not alter the db
            $sync->setPerformUpdate($this->perform);

            // Set Verbosity so you can see intentions
            $sync->setVerbose($this->verbose);

            $sync->generateTitle();
            $sync->synchronise();

            echo "\n\n";
        }
    }


    /**
     * This method is designed to provide the developer with information about the setup of the Synergetic database
     * to be able to set the details of the sync-script to align with the Synergetic setup.
     */
    public function diagnose() {
        // for the sake of modularity, this is split out into subordinate methods
        $refl = new ReflectionObject($this);
        foreach ($refl->getMethods(ReflectionMethod::IS_PRIVATE) as $m) {
            $meth = $m->name;
            if (strpos($meth, $this->db) !== false || strpos($meth, 'Schoolbox') !== false) {
                $this->$meth();
            }
        }
    }


    /* all private methods with 'Schoolbox' or the current db name will be picked up by the reflection in diagnose() */

    private function lookupSynergeticCampusCodes() {
        $db = MSSQL::getInstance();
        $campuses = $db->getAll("SELECT DISTINCT( ClassCampus ) AS campus FROM timetable");
        echo "Campus Codes in Synergetic\n";
        foreach ($campuses as $c) {
            echo "  '" . $c['campus'] . "'\n";
        }

        $db = Database::getInstance();

        $result = $db->query("SELECT * FROM `luCampus`");
        if ($result) {
            echo "Campuses listed in Schoolbox\n";
            printf("%4s %-32s\n", 'id', 'campus name');
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                printf("%4s %-32s\n", $row['id'], $row['name']);
            }
        }
    }

    private function lookupSynergeticStudentExample() {
        echo "Synergetic Student Entity Examples\n";
        $esu = new External_Synergetic_User();
        $esu->getCurrentSemester();
        $students = $esu->getStudentUserAccountInfo();

        foreach (array_keys($students[0]) as $key) {
            printf("%-24s", $key);
        }
        echo "\n";

        $j = $k = 0;
        for ($i = 0; $i < count($students) && $j < 5; ++$i) {
            if ($students[$i]['NetworkLogin']) {
                foreach ($students[$i] as $field) {
                    printf("%-24s", $field);
                }
                echo "\n";
                ++$j;
            } else {
                ++$k;
            }
        }
        if ($k) {
            echo "$k skipped with no NetworkLogin\n";
        }
    }

    private function lookupSynergeticStaffExample() {
        echo "Synergetic Staff Entity Examples\n";
        $esu = new External_Synergetic_User();
        $esu->getCurrentSemester();
        $students = $esu->getStaffUserAccountInfo();

        foreach (array_keys($students[0]) as $key) {
            printf("%-24s", $key);
        }
        echo "\n";

        $j = $k = 0;
        for ($i = 0; $i < count($students) && $j < 5; ++$i) {
            if ($students[$i]['NetworkLogin']) {
                foreach ($students[$i] as $field) {
                    printf("%-24s", $field);
                }
                echo "\n";
                ++$j;
            } else {
                ++$k;
            }
        }
        if ($k) {
            echo "$k skipped with no NetworkLogin\n";
        }
    }

    private function lookupSynergeticParentExample() {
        echo "Synergetic Parent Entity Examples\n";
        $esu = new External_Synergetic_User();
        $esu->getCurrentSemester();
        $students = $esu->getParentUserAccountInfo();

        foreach (array_keys($students[0]) as $key) {
            printf("%-24s", substr($key, 7));
        }
        echo "\n";

        for ($i = 0; $i < 5; $i++) {
            foreach ($students[$i] as $field) {
                printf("%-24s", $field);
            }
            echo "\n";
        }
    }

    private function lookupSynergeticStaffCategory() {
        echo "Synergetic Staff Category List\n";
        $esu = new External_Synergetic_User();
        $esu->getCurrentSemester();
        $staff = $esu->getStaffUserAccountInfo();
        $cats = array();

        for($i = 0; $i < count($staff); ++$i) {
            if(isset($cats[$staff[$i]['StaffCategory']])) {
                continue;
            }
            $cats[$staff[$i]['StaffCategory']] = true;
        }

        if(count($cats)) {
            foreach($cats as $cat => $isTrue) {
                echo "  $cat\n";
            }
        }
    }

    private final function lookupSchoolboxGroupsFromYearLevel() {
        $db = Database::getInstance();

        $result = $db->query("SELECT * FROM `groups` WHERE `group_type` = 'student'");

        if ($result) {
            echo "Student Groups in Schoolbox\n";
            printf("%4s %-32s\n", 'id', 'group name');
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                printf("%4s %-32s\n", $row['id'], $row['name']);
            }
        } else {
            echo "Failed to obtain Schoolbox student groups\n";
        }
    }
}
