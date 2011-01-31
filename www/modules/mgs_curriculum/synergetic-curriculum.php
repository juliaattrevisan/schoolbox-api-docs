<?php
require_once SITE_root .'/library/mssql.php';

class SynergeticCurriculum extends MSSQL{

	function SynergeticCurriculum() {
            parent::MSSQL();
	}

        function search($keyword) {
            $query = "SELECT SubjectAssessmentsCode FROM uSubjectCurricMap1 WHERE
                UnitName LIKE '%$keyword%' OR
                UnitCont LIKE '%$keyword%' OR
                Skills LIKE '%$keyword%' OR
                Assessment LIKE '%$keyword%' OR
                Resources LIKE '%$keyword%' OR
            UnitName1 LIKE '%$keyword%' OR
                UnitCont1 LIKE '%$keyword%' OR
                Skills1 LIKE '%$keyword%' OR
                Assessment1 LIKE '%$keyword%' OR
                Resources1 LIKE '%$keyword%' OR
            UnitName2 LIKE '%$keyword%' OR
                UnitCont2 LIKE '%$keyword%' OR
                Skills2 LIKE '%$keyword%' OR
                Assessment2 LIKE '%$keyword%' OR
                Resources2 LIKE '%$keyword%' OR
            UnitName3 LIKE '%$keyword%' OR
                UnitCont3 LIKE '%$keyword%' OR
                Skills3 LIKE '%$keyword%' OR
                Assessment3 LIKE '%$keyword%' OR
                Resources3 LIKE '%$keyword%' OR
            UnitName4 LIKE '%$keyword%' OR
                UnitCont4 LIKE '%$keyword%' OR
                Skills4 LIKE '%$keyword%' OR
                Assessment4 LIKE '%$keyword%' OR
                Resources4 LIKE '%$keyword%' OR
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
            ";
            return $this->getAll($query);
        }

        function get($code) {

            $query = "SELECT * FROM uSubjectCurricMap1 WHERE SubjectAssessmentsCode = '$code'";

            return $this->getRow($query);

        }
}
?>
