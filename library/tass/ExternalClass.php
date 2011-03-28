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
		if(stristr($subCode, ',')) {
				$subCodeArray = explode(',',$subCode);
				foreach($subCodeArray as $code) {
                                        list($year,$code,$class) = explode('-',$code);
                                        $classQuery .= " ( year_grp = '$year' AND studsub.sub_code = '$code' AND class = '$class' ) OR ";
				}
				$classQuery .= '1=0';
		}
		else {
                    list($year,$code,$class) = explode('-',$subCode);
                    $classQuery = " AND year_grp = '$year' AND studsub.sub_code = '$code' AND class = '$class' ";
                }

            

                $query = "SELECT studsub.stud_code as synergy_id, preferred_name as firstname, surname as lastname, year_grp as yearlevel, house
                FROM studsub
                JOIN student ON student.stud_code = studsub.stud_code AND student.cmpy_code = studsub.cmpy_code
                WHERE semester = '{$_SESSION['FileSemester']}' AND yr_study = '{$_SESSION['FileYear']}'
                    AND ( $classQuery )
                ORDER BY lastname, firstname";

		return $this->getAll($query);
	}
	
	function getTeacherName($subCode) {

		if(stristr($subCode, ',')) {
				$subCodeArray = explode(',',$subCode);
				foreach($subCodeArray as $code) {
                                        list($year,$code,$class) = explode('-',$code);
                                        $classQuery .= " ( year_grp = '$year' AND studsub.sub_code = '$code' AND class = '$class' ) OR ";
				}
				$classQuery .= '1=0';
		}
		else {
                    list($year,$code,$class) = explode('-',$subCode);
                    $classQuery = " AND year_grp = '$year' AND studsub.sub_code = '$code' AND class = '$class' ";
                }


            list($year, $code, $class) = explode('-',$subCode);
            $query = "SELECT tch_code FROM tchsub WHERE semester = '{$_SESSION['FileSemester']}' AND year_num = '{$_SESSION['FileYear']}'
                           AND ( $classQuery )";

            $this->query($query);
            while($synrow = $this->getNextArray() ) {
                    $teachers[] = $synrow['tch_code'];
            }
            return $teachers;
	}
}

?>
