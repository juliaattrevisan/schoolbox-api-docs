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

            $classname = sprintf($this->stratBase, $this->db, $this->school, $currentStrategy);

            if (!class_exists($classname)) {
                $classname = str_replace('_', '\\', $classname);

                if (!class_exists($classname)) {
                    printf("Unable to find strat: %s.%s", $this->school, $currentStrategy);
                    continue;
                }
            }

            // double-nested polymorphism anyone? This may need refactoring.
            // this will also call setup() in some cases, which is reserved for destructive changes
            $strats[$currentStrategy] = new $facade($classname);
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
 
    private function lookupSynergeticTimetableDefinition() {
        $db = new MSSQL();
        $codes = $db->getAll("SELECT * FROM luTimetableGroup");
        echo "Timetable Definition in Synergetic\n";
        echo "  Code\tCycle\tPeriods\tActive\tDescription\n";
        foreach ($codes as $c) {
            echo "  '" . $c['Code'] ."'\t'" . $c['CycleDays'] . "'\t'" . $c['Periods'] . "'\t'" . $c['ActiveFlag'] . "'\t'" . $c['Description'] . "'\n";
        }
    }
    
    private function lookupSynergeticHouseCodes() {
        $db = new MSSQL();
        $codes = $db->getAll("SELECT Code, Description FROM luHouse WHERE ActiveFlag='1' GROUP BY Code,Description");
        echo "House Codes in Synergetic\n";
        foreach ($codes as $c) {
            echo "  '" . $c['Code'] . "'\t'" . $c['Description'] . "'\n";
        }
    }

    private function lookupSynergeticStudentExample() {
        echo "Synergetic Student Entity Examples\n";
        $esu = new External_Synergetic_User();
        $esu->getCurrentSemester();

        $this->renderTable($esu->getStudentUserAccountInfo());
    }

    private function lookupSynergeticStaffExample() {
        echo "Synergetic Staff Entity Examples\n";
        $esu = new External_Synergetic_User();
        $esu->getCurrentSemester();

        $this->renderTable($esu->getStaffUserAccountInfo());
    }

    private function lookupSynergeticParentExample() {
        echo "Synergetic Parent Entity Examples\n";
        $esu = new External_Synergetic_User();
        $esu->getCurrentSemester();

        $this->renderTable($esu->getParentUserAccountInfo(), null, 10, 7);
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


    /**
     * @param array  $dataset
     * @param string $notBlankField
     * @param int    $maxRows
     * @param int    $offset
     */
    private function renderTable($dataset, $notBlankField = 'NetworkLogin', $maxRows = 10, $offset = 0)
    {
        $padding = 3;

        // find field-widths per key
        $keyWidths = array();
        foreach ($dataset as $record) {
            if ($notBlankField && !$record[$notBlankField]) {
                continue;
            }
            foreach ($record as $col => $val) {
                if (empty($keyWidths[$col])) {
                    $keyWidths[$col] = strlen(substr($col, $offset));
                }
                $keyWidths[$col] = max($keyWidths[$col], strlen($val));
            }
        }

        foreach ($keyWidths as $key => $width) {
            $w = $width + $padding;
            printf("%-{$w}s", substr($key, $offset));
        }
        echo "\n";

        $j = $k = 0;
        for ($i = 0; $i < count($dataset) && $j < $maxRows; ++$i) {
            if ($notBlankField && !$dataset[$i][$notBlankField]) {
                ++$k;
            } else {
                foreach ($dataset[$i] as $key => $field) {
                    $w = $keyWidths[$key] + $padding;
                    printf("%-{$w}s", $field);
                }
                echo "\n";
                ++$j;
            }
        }
        if ($k) {
            echo "$k skipped with no $notBlankField\n";
        }

        echo "\n";
    }
}
