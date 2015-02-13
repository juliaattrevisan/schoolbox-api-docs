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

	function __construct () {
		$this->setDatabase(MSSQL::getInstance());
	}
	
	function getStudents($subCode) {
        $classQuery = '';
		if(stristr($subCode, ',')) {
			$subCodeArray = explode(',',$subCode);
			foreach($subCodeArray as $code) {
				list($year,$code,$class) = explode('-',$code);
				$yearQuery = $year !== '' ?  "year_grp = '$year' AND" : '';

				$classQuery .= " ( $yearQuery studsub.sub_code = '$code' AND class = '$class' ) OR ";
			}
			$classQuery .= '1=0';
		}
		else {
			list($year,$code,$class) = explode('-',$subCode);
            $yearQuery = $year !== '' ?  "year_grp = '$year' AND" : '';

			$classQuery = " $yearQuery studsub.sub_code = '$code' AND class = '$class' ";
		}



		$query = "SELECT DISTINCT studsub.stud_code as synergy_id, preferred_name as firstname, surname as lastname, year_grp as yearlevel, house
		FROM studsub
		JOIN student ON student.stud_code = studsub.stud_code AND student.cmpy_code = studsub.cmpy_code
		WHERE semester = '{$_SESSION['FileSemester']}'
			AND yr_study = '{$_SESSION['FileYear']}'
			AND next_yr_ind != 'F'
			AND ( $classQuery )
		ORDER BY lastname, firstname";

		return $this->getDatabase()->getAll($query);
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
		$query = "SELECT DISTINCT tch_code FROM tchsub WHERE semester = '{$_SESSION['FileSemester']}' AND year_num = '{$_SESSION['FileYear']}'
					   AND ( $classQuery )";

		$this->getDatabase()->query($query);
		while($synrow = $this->getDatabase()->getNextArray() ) {
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
