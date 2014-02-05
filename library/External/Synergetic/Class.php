<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to synergetic class info        *
 *                                                           *
 ************************************************************/

use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_Class implements External_ClassInterface
{

    private $database;

    function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        if (DebugBar::isEnabled()) {
            $this->getDatabase()->installCollector();
            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collector = $this->getDatabase()->getCollector();
            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }
    }

	public function getClass($classCode) {
		$query = "
			SELECT *
			FROM SynergyOne.dbo.SubjectClasses
			WHERE ClassCode = '$classCode'
			AND FileYear='" . $_SESSION['FileYear'] . "'
			AND FileSemester='" . $_SESSION['FileSemester'] . "'
		";

		return $this->getDatabase()->getRow($query);
	}

    public function getStudents($subCode)
    {
	    if(!$subCode) {
		    /* Dont bother if you got no code */
		    return false;
	    }

        $classQuery = '(';

	    $subCodeArray = explode(',', $subCode);
        foreach ($subCodeArray as $code) {

            if(strpos($code, '/') !== FALSE ) {
                $codeArray = explode('/',$code);
                $classQuery .= "( StudentClasses.ClassCode = '{$codeArray[1]}' AND StudentClasses.ClassCampus = '{$codeArray[0]}' ) OR ";
            }
            else {
                $classQuery .= "StudentClasses.ClassCode = '$code' OR ";
            }
        }

        $classQuery = substr($classQuery, 0, -3) . ')';

        $query = "
		SELECT DISTINCT StudentSurname as lastname, StudentPreferred as firstname,
                        StudentYearLevelDescription as yearlevel, StudentHouseDescription as house,
                        StudentMedicalAlert, StudentSpecialNeedsFlag,
                        StudentID as synergy_id, ClassCampus
		FROM    SynergyOne.dbo.StudentClasses StudentClasses
		JOIN SynergyOne.dbo.vStudents vStudents ON (
			vStudents.FileYear = StudentClasses.FileYear AND
			vStudents.FileSemester = StudentClasses.FileSemester AND
			vStudents.ID = StudentClasses.ID)
		WHERE 
			StudentStatus IN ('N','NOR','NEW','RET','LV','LEA','RPT','FUL','CUR','REN','PAT', 'EXG')
			AND $classQuery
			AND (StudentClasses.FileYear='" . $_SESSION['FileYear'] . "')
			AND (StudentClasses.FileSemester='" . $_SESSION['FileSemester'] . "')
			AND (StudentClasses.FileType != 'A' OR StudentClasses.StartDate <= getdate())
			AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
		ORDER BY StudentSurname, StudentPreferred";

        return $this->getDatabase()->getAll($query);
    }

    public function getTeacherName($subCode)
    {
        $teachers = array();

        $classQuery = '(';
        $subCodeArray = explode(',', $subCode);
        foreach ($subCodeArray as $code) {

            if(strpos($code, '/') !== FALSE ) {
                $codeArray = explode('/',$code);
                $classQuery .= "( ClassCode = '{$codeArray[1]}' AND ClassCampus = '{$codeArray[0]}' ) OR ";
            }
            else {
                $classQuery .= "ClassCode = '$code' OR ";
            }
        }

        $classQuery = substr($classQuery, 0, -3) . ')';

        $msQuery = "SELECT DISTINCT StaffID, StaffSeq FROM vSubjectClassesStaff
                    WHERE $classQuery AND FileYear={$_SESSION['FileYear']} AND FileSemester={$_SESSION['FileSemester']}
                    ORDER BY StaffSeq";

        $this->getDatabase()->query($msQuery);

        while ($synrow = $this->getDatabase()->getNextArray()) {
            if (is_numeric($synrow['StaffID'])) {
                $teachers[] = $synrow['StaffID'];
            }
        }

        return $teachers;
    }

    private function setDatabase($database)
    {
        $this->database = $database;
    }

    private function getDatabase()
    {
        return $this->database;
    }

	/**
	 * Get all the filetypes in Synergetic
	 *
	 * @return mixed
	 */
	public function findFiletypes() {
		$query = "
			SELECT *
			FROM SynergyOne.dbo.luFileType
			WHERE Code != ''
		";

		return $this->getDatabase()->getAll($query);
	}

	/**
	 * Get all the subjects with a particular filetype
	 *
	 * @param $filetype
	 * @return mixed
	 */
	public function findSubjectsWithFiletype($filetype) {
		$query = "
			SELECT *
			FROM SynergyOne.dbo.StudentClasses
			WHERE FileType = '{$filetype}'
			ORDER BY ClassCode
		";

		return $this->getDatabase()->getAll($query);
	}
}