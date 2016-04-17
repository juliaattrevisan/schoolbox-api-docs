<?php
/**
 * Synchronise_Synergetic_Tintern_Strategy_Parent
 *
 * Update Schoolbox with Synergetic data if there is something different
 * contained in Synergetic database.
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Tintern_Strategy_Parent extends Synchronise_Base_Strategy_Parent {

    /**
     * @var MSSQL
     */
    private $database;

    protected $title = "Update Schoolbox Parent Details Synchronisation";

    /**
     * Used to retrieve student campus out of each parent.
     *
     * @var Synchronise_Synergetic_Tintern_Strategy_Student
     */
    protected $student_strategy;

    public function __construct() {
        parent::__construct();
        $this->setDatabase(MSSQL::getInstance());
    }

    /**
     * Setup verbose output
     */
    protected function setup() {
        $this->verbose_filter   = new Synchronise_Synergetic_Tintern_Verbose();
        $this->student_strategy = new Synchronise_Synergetic_Tintern_Strategy_Student();
    }

    /**
     * Overloaded to insert password for parents
     * @param array $currentUser
     * @return array
     */
    public function setupUserDetails($currentUser) {
        $newUser = parent::setupUserDetails($currentUser);
        // Under no circumstances clobber an existing user's password
        if (!$this->userExists($newUser)) {
            $newUser['password'] = $this->getPassword($newUser);
        } else {
            unset($newUser['password']);
        }
        return $newUser;
    }

    /**
     * Overload these for the purpose of setting the password up for parents
     * @param array $currentUser
     * @return array
     */
    public function setupSpouseDetails($currentUser) {
        $newUser = parent::setupSpouseDetails($currentUser);
        // Under no circumstances clobber an existing user's password
        if (!$this->userExists($newUser)) {
            $newUser['password'] = $this->getPassword($newUser);
        } else {
            unset($newUser['password']);
        }
        return $newUser;
    }

    /**
     * Set password based on the following rules
     * First 4 chars of surname, padded with - if not enough, last 3 digits of synergy id.
     * @param array $newUser
     * @return string
     */
    public function getPassword($newUser) {
        return  str_pad(substr(preg_replace('/[^\w\d]/ui','',strtolower($newUser['lastname'])),0,4),4,'-') . substr($newUser['synergy_id'],-3);
    }

    /**
     * Gets default campus in same method as it's retrieved from student.
     *
     * @param  array $currentUser
     * @return int
     */
    public function getDefaultCampusFromUser($currentUser) {
        return $this->student_strategy->getDefaultCampusFromUser($currentUser);
    }

	/**
	 * Webmail setting
	 *
	 * @param $currentUser
	 * @return int|void
	 */
	public function getWebmailFromUser($currentUser) {
		return 0;
	}

	/**
	 * External mail setting
	 *
	 * @param $currentUser
	 * @return int|void
	 */
	public function getExternalMailFromUser($currentUser) {
		return 0;
	}

    /**
     * Set default parent group
     * @param array $currentUser
     * @return int
     */
    public function getGroupIDFromUser($currentUser) {
        return 19;
    }

    /**
     * Set default parent group
     * @param array $currentUser
     * @return int
     */
    public function getSpouseGroupIDFromUser($currentUser) {
        return 19;
    }

    /**
     * Get a list of users' Synergy IDs to sync
     *
     * @return array
     */
    public function generateList() {

        // replicate the query from the synergetic user handler but include SC3 contact type

        // Get current FileSemester and FileYear

        $query = "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1";

        if ($semester = $this->getDatabase()->getRow($query))
        {
            $query = "
                SELECT DISTINCT
                    cp.NetworkLogin AS StudentContactNetworkLogin,
                    s.StudentContactID,
                    s.StudentContactTitle,
                    s.StudentContactPreferred,
                    s.StudentContactSurname,
                    s.StudentContactDefaultEmail,
                    cs.NetworkLogin AS StudentContactSpouseNetworkLogin,
                    s.StudentContactSpouseID,
                    s.StudentContactSpouseTitle,
                    s.StudentContactSpousePreferred,
                    s.StudentContactSpouseSurname,
                    s.StudentContactSpouseDefaultEmail,
                    s.StudentYearLevel,
                    s.StudentCampus,
                    s.StudentGender
                FROM SynergyOne.dbo.vStudentContactAllAddress s
                LEFT JOIN SynergyOne.dbo.Community cp ON s.StudentContactID = cp.ID
                LEFT JOIN SynergyOne.dbo.Community cs ON s.StudentContactSpouseID = cs.ID
                WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2' OR StudentContactType = 'SC3')
                AND FileSemester = '{$semester['FileSemester']}'
                AND FileYear = '{$semester['FileYear']}'
                ORDER BY StudentContactID
            ";

            return $this->getDatabase()->getAll($query);
        }

        return [];
    }

    private function setDatabase($database) {
        $this->database = $database;
    }

    /**
     * Get database
     *
     * @return MSSQL
     */
    private function getDatabase() {
        return $this->database;
    }
}
