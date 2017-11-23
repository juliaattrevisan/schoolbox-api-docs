<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2013 Alaress Pty Ltd  				*
*                                                           *
*	This file provides access to TASS class info		*
*                                                           *
************************************************************/

class External_Tass_Class implements External_ClassInterface {
	/**
	 * @var MSSQL
	 */
	private $database;

    /**
     * @var External_UserInterface
     */
    private $externalUser;

	function __construct () {
		$this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('tass');
	}

    function getStudents($subCode) {
        $classQuery = '';
		if(stristr($subCode, ',')) {
			$subCodeArray = explode(',',$subCode);
			foreach($subCodeArray as $code) {
				list($year,$code,$class) = explode('-',$code);
				$yearQuery = $year !== '' ?  "student.year_grp = '$year' AND" : '';

				$classQuery .= " ( $yearQuery studsub.sub_code = '$code' AND studsub.class = '$class' ) OR ";
			}
			$classQuery .= '1=0';
		}
		else {
			list($year,$code,$class) = explode('-',$subCode);
            $yearQuery = $year !== '' ?  "student.year_grp = '$year' AND" : '';

			$classQuery = " $yearQuery studsub.sub_code = '$code' AND studsub.class = '$class' ";
		}


        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear());
		$query = "SELECT DISTINCT studsub.stud_code as synergy_id, student.preferred_name as firstname, student.surname as lastname, student.year_grp as yearlevel, student.house, '' as StudentTutorDescription
		FROM studsub
		JOIN student ON student.stud_code = studsub.stud_code AND student.cmpy_code = studsub.cmpy_code
		WHERE semester = '{$fileSemester}'
			AND yr_study = '{$fileYear}'
			AND next_yr_ind != 'F'
			AND ( $classQuery )
		ORDER BY lastname, firstname";

		return $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
	}
	
	function getTeacherName($subCode) {
		$classQuery = '';
		if(stristr($subCode, ',')) {
			$subCodeArray = explode(',',$subCode);
			foreach($subCodeArray as $code) {
				list($year,$code,$class) = explode('-',$code);
                $yearQuery = $year !== '' ?  "year_grp = '$year' AND" : '';


				$classQuery .= " ( $yearQuery sub_code = '$code' AND class = '$class' ) OR ";
			}
			$classQuery .= '1=0';
		}
		else {
			list($year,$code,$class) = explode('-',$subCode);
            $yearQuery = $year !== '' ?  "year_grp = '$year' AND" : '';

			$classQuery = " $yearQuery sub_code = '$code' AND class = '$class' ";
		}


		list($year, $code, $class) = explode('-',$subCode);
        $fileSemester = $this->database->quote($this->externalUser->getCurrentSemester());
        $fileYear     = $this->database->quote($this->externalUser->getCurrentYear());
		$query = "SELECT DISTINCT tch_code FROM tchsub WHERE semester = '{$fileSemester}' AND year_num = '{$fileYear}'
					   AND ( $classQuery )";

        $statement = $this->getDatabase()->query($query);
        $teachers = [];
		while ($synrow = $statement->fetch()) {
			$teachers[] = $synrow['tch_code'];
		}

		return array_unique($teachers);
	}

	private function setDatabase ($database) {
		$this->database = $database;
	}

	private function getDatabase () {
		return $this->database;
	}
}
