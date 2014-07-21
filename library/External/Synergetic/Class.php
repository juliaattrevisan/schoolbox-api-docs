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

    /**
     * Returns the details of a class based on its code
     * This function is Synergetic only, not included in the interface and used for attendances
     * @param $classCode
     * @return mixed
     */
    public function getClass($classCode) {

        if(strpos($classCode, '/') !== FALSE ) {
            $codeArray = explode('/',$classCode);
            $classQuery .= "( ClassCode = '{$codeArray[1]}' AND ClassCampus = '{$codeArray[0]}' ) ";
        }
        else {
            $classQuery .= "ClassCode = '$classCode'";
        }

		$query = "
			SELECT *
			FROM SynergyOne.dbo.SubjectClasses
			WHERE $classQuery
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
                $classQuery .= "( ClassCode = '{$codeArray[1]}' AND ClassCampus = '{$codeArray[0]}' ) OR ";
            }
            else {
                $classQuery .= "ClassCode = '$code' OR ";
            }
        }

        $classQuery = substr($classQuery, 0, -3) . ')';

        $query = "
		SELECT DISTINCT StudentClasses.StudentSurname as lastname, StudentClasses.StudentPreferred as firstname,
                        StudentClasses.StudentYearLevelDescription as yearlevel, StudentClasses.StudentHouseDescription as house,
                        vStudents.StudentMedicalAlert, vStudents.StudentSpecialNeedsFlag,
                        StudentClasses.StudentID as synergy_id, StudentClasses.ClassCampus
		FROM    SynergyOne.dbo.vStudentClasses StudentClasses
		JOIN SynergyOne.dbo.vStudents vStudents ON (
			vStudents.FileYear = StudentClasses.FileYear AND
			vStudents.FileSemester = StudentClasses.FileSemester AND
			vStudents.ID = StudentClasses.StudentID)
		WHERE $classQuery
			AND (StudentClasses.FileYear='" . $_SESSION['FileYear'] . "')
			AND (StudentClasses.FileSemester='" . $_SESSION['FileSemester'] . "')
			AND (StudentClasses.StartDate <= getdate() OR StudentClasses.StartDate IS NULL)
			AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
		ORDER BY StudentClasses.StudentSurname, StudentClasses.StudentPreferred";

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

        return array_unique($teachers);
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
			SELECT ClassCode
			FROM SynergyOne.dbo.StudentClasses
			WHERE FileType = '{$filetype}'
			AND FileYear = '{$_SESSION['FileYear']}'
			AND FileSemester = '{$_SESSION['FileSemester']}'
			GROUP BY ClassCode
			ORDER BY ClassCode
		";

		return $this->getDatabase()->getAll($query);
	}
}