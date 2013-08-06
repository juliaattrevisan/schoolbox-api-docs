<?php

class SynergeticCurriculum extends MSSQL{

	function SynergeticCurriculum() {
            parent::MSSQL();
	}

        function search($keyword) {

            //$query = "sp_columns SubjectAssessments";
           // print_r($this->getAll($query));

            $query = "SELECT '', SubjectAssessmentsCode, Heading, c.UnitName, YearLevel FROM uSubjectCurricMap1 c
            JOIN SubjectAssessments a ON a.AssessmentCode=c.SubjectAssessmentsCode
            AND a.FileType=c.FileType AND a.FileYear=c.FileYear AND a.FileSemester=c.FileSemester AND SubjectAssessmentsCode != ''
            WHERE
                (c.FileSemester=".$_SESSION['FileSemester']." AND c.FileYear=".$_SESSION[ 'FileYear'].") AND (
                c.UnitName LIKE '%$keyword%' OR
                UnitCont LIKE '%$keyword%' OR
                Skills LIKE '%$keyword%' OR
                Assessment LIKE '%$keyword%' OR
                Resources LIKE '%$keyword%')";

            $complete  = $this->getAll($query);


            $query = "SELECT '1', SubjectAssessmentsCode, Heading, c.UnitName1 UnitName, YearLevel FROM uSubjectCurricMap1 c
            JOIN SubjectAssessments a ON a.AssessmentCode=c.SubjectAssessmentsCode
            AND a.FileType=c.FileType AND a.FileYear=c.FileYear AND a.FileSemester=c.FileSemester AND SubjectAssessmentsCode != ''
            WHERE
            (c.FileSemester=".$_SESSION['FileSemester']." AND c.FileYear=".$_SESSION[ 'FileYear'].") AND (
            UnitName1 LIKE '%$keyword%' OR
                UnitCont1 LIKE '%$keyword%' OR
                Skills1 LIKE '%$keyword%' OR
                Assessment1 LIKE '%$keyword%' OR
                Resources1 LIKE '%$keyword%')";
            $result = $this->getAll($query);
            if(is_array($result))
                $complete  = array_merge($complete, $result);

           $query = "SELECT '2', SubjectAssessmentsCode, Heading, c.UnitName2 UnitName, YearLevel FROM uSubjectCurricMap1 c
            JOIN SubjectAssessments a ON a.AssessmentCode=c.SubjectAssessmentsCode
            AND a.FileType=c.FileType AND a.FileYear=c.FileYear AND a.FileSemester=c.FileSemester AND SubjectAssessmentsCode != ''
            WHERE
            (c.FileSemester=".$_SESSION['FileSemester']." AND c.FileYear=".$_SESSION[ 'FileYear'].") AND (
            UnitName2 LIKE '%$keyword%' OR
                UnitCont2 LIKE '%$keyword%' OR
                Skills2 LIKE '%$keyword%' OR
                Assessment2 LIKE '%$keyword%' OR
                Resources2 LIKE '%$keyword%')";
            $result = $this->getAll($query);
            if(is_array($result))
                $complete  = array_merge($complete, $result);

            $query = "SELECT '3', SubjectAssessmentsCode, Heading, c.UnitName3 UnitName, YearLevel FROM uSubjectCurricMap1 c
            JOIN SubjectAssessments a ON a.AssessmentCode=c.SubjectAssessmentsCode
            AND a.FileType=c.FileType AND a.FileYear=c.FileYear AND a.FileSemester=c.FileSemester AND SubjectAssessmentsCode != ''
            WHERE
            (c.FileSemester=".$_SESSION['FileSemester']." AND c.FileYear=".$_SESSION[ 'FileYear'].") AND (
            UnitName3 LIKE '%$keyword%' OR
                UnitCont3 LIKE '%$keyword%' OR
                Skills3 LIKE '%$keyword%' OR
                Assessment3 LIKE '%$keyword%' OR
                Resources3 LIKE '%$keyword%')";
            $result = $this->getAll($query);
            if(is_array($result))
                $complete  = array_merge($complete, $result);

           $query = "SELECT '4', SubjectAssessmentsCode, Heading, c.UnitName4 UnitName, YearLevel FROM uSubjectCurricMap1 c
            JOIN SubjectAssessments a ON a.AssessmentCode=c.SubjectAssessmentsCode
            AND a.FileType=c.FileType AND a.FileYear=c.FileYear AND a.FileSemester=c.FileSemester AND SubjectAssessmentsCode != ''
            WHERE
            (c.FileSemester=".$_SESSION['FileSemester']." AND c.FileYear=".$_SESSION[ 'FileYear'].") AND (
            UnitName4 LIKE '%$keyword%' OR
                UnitCont4 LIKE '%$keyword%' OR
                Skills4 LIKE '%$keyword%' OR
                Assessment4 LIKE '%$keyword%' OR
                Resources4 LIKE '%$keyword%')";
            $result = $this->getAll($query);
            if(is_array($result))
                $complete  = array_merge($complete, $result);

           /*
            UnitName5 LIKE '%$keyword%' OR
                UnitCont5 LIKE '%$keyword%' OR
                Skills5 LIKE '%$keyword%' OR
                Assessment5 LIKE '%$keyword%' OR
                Resources5 LIKE '%$keyword%' OR
            UnitName6 LIKE '%$keyword%' OR
                UnitCont6 LIKE '%$keyword%' OR
                Skills6 LIKE '%$keyword%' OR
                Assessment6 LIKE '%$keyword%' OR
                Resources6 LIKE '%$keyword%' OR
            UnitName7 LIKE '%$keyword%' OR
                UnitCont7 LIKE '%$keyword%' OR
                Skills7 LIKE '%$keyword%' OR
                Assessment7 LIKE '%$keyword%' OR
                Resources7 LIKE '%$keyword%' OR
            UnitName8 LIKE '%$keyword%' OR
                UnitCont8 LIKE '%$keyword%' OR
                Skills8 LIKE '%$keyword%' OR
                Assessment8 LIKE '%$keyword%' OR
                Resources8 LIKE '%$keyword%'
            ";*/
            //$result  = $this->getAll($query);
            //print_r($complete);
            return $complete;
        }

        function get($code) {

            $query = "SELECT * FROM uSubjectCurricMap1 c
            JOIN SubjectAssessments a ON a.AssessmentCode=c.SubjectAssessmentsCode
            AND a.FileType=c.FileType AND a.FileYear=c.FileYear AND a.FileSemester=c.FileSemester
            WHERE
            c.FileSemester=".$_SESSION['FileSemester']." AND
            c.FileYear=".$_SESSION[ 'FileYear']." AND
            SubjectAssessmentsCode = '$code'";

            return $this->getRow($query);

        }
}
?>
