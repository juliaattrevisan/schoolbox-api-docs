<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic class info		*	    
*                                                           *
************************************************************/
require_once dirname(__FILE__) .'/../mssql.php';


class ExternalClass extends MSSQL{

	function __construct() {
		parent::MSSQL();
	}
	
	function getStudents($subCode) {

            /*
		if(stristr($subCode, ',')) {
				$classQuery = 'StudentClasses.ClassCode IN(';
				$subCodeArray = explode(',',$subCode);
				foreach($subCodeArray as $code) {
					$classQuery .= "'$code',";
				}
				$classQuery = substr($classQuery,0,-1) . ')';
		}
		else
			$classQuery = "StudentClasses.ClassCode = '$subCode'";
            */

            list($year,$code,$class) = explode('-',$subCode);

                $query = "SELECT studsub.stud_code as synergy_id, preferred_name as firstname, surname as lastname, year_grp as yearlevel, house
                FROM studsub
                JOIN student ON student.stud_code = studsub.stud_code AND student.cmpy_code = studsub.cmpy_code
                WHERE semester = '{$_SESSION['FileSemester']}' AND yr_study = '{$_SESSION['FileYear']}'
                    AND year_grp = '$year' AND studsub.sub_code = '$code' AND class = '$class'
                ORDER BY lastname, firstname";

		return $this->getAll($query);
	}
	
	function getTeacherName($subCode) {

            /*if(stristr($subCode, ',')) {
                $classQuery = 'ClassCode IN(';
                $subCodeArray = explode(',',$subCode);
                foreach($subCodeArray as $code) {
                        $classQuery .= "'$code',";
                }
                $classQuery = substr($classQuery,0,-1) . ')';
            }
            else
                $classQuery = "ClassCode = '$subCode'";
            */
            list($year, $code, $class) = explode('-',$subCode);
            $query = "SELECT tch_code FROM tchsub WHERE semester = '{$_SESSION['FileSemester']}' AND year_num = '{$_SESSION['FileYear']}'
                            AND year_grp = '$year' AND sub_code = '$code' AND class='$class'";

            $this->query($query);
            while($synrow = $this->getNextArray() ) {
                    $teachers[] = $synrow['tch_code'];
            }
            return $teachers;
	}
}

?>
