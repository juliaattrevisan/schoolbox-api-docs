<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

class External_Synergetic_Markbook implements External_MarkbookInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * @var External_User
     */
    private $externalUser;

    /**
     * Constructor
     */
    public function __construct()
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

        $this->setExternalUser(new External_User);
    }

    /**
     * Returns the assessment information if found in remote system
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function getAssessment(\Schoolbox\Assessment\Component\SubmissionBox $assessment)
    {
        $query = "SELECT * FROM StaffAssessmentMarkBook WHERE HdgAbbrev2 = '".$assessment->getId()."'";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * Creates a new assessment item in the markbook
     *
     * @param \Schoolbox\User
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function createAssessment(\Schoolbox\Assessment\Component\SubmissionBox $assessment)
    {
        $folder = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($assessment->getCategory());
        $externalClass = new External_Synergetic_Class();

        $fileYear = $this->getExternalUser()->getCurrentYear($assessment->getOwner()->getExternalId());
        $fileSemester = $this->getExternalUser()->getCurrentSemester($assessment->getOwner()->getExternalId());

        foreach($folder->getCodes() as $classCode) {

            $class = $externalClass->getClass($classCode);

            $query = "INSERT INTO StaffAssessmentMarkBook (
                        FileType,
                        FileYear,
                        FileSemester,
                        StaffID,
                        ClassCampus,
                        ClassCode,
                        MarkBookSeq,
                        Heading,
                        HdgAbbrev1,
                        HdgAbbrev2,
                        ResultType,
                        MarkOutOf
                    ) VALUES (
                      '{$class['FileType']}',
                      '{$fileYear}',
                      '{$fileSemester}',
                      ".$assessment->getOwner()->getExternalId().",
                      '{$class['ClassCampus']}',
                      '{$class['ClassCode']}',
                      '".$this->getStaffMarkBookSequence($class['FileType'], $fileYear, $fileSemester, $class['ClassCampus'], $class['ClassCode'] )."',
                      '".$this->getDatabase()->quote($assessment->getDescription())."',
                      '".$this->getDatabase()->quote(TextFormatter::cutString($assessment->getDescription(),15)).",
                      '".$assessment->getId()."',
                      'Raw',
                      '100'
                    )
                    ";
            $this->getDatabase()->query($query);
        }

    }

    /**
     *
     * Fetches the current total count of items in the marbook to determine the next sequence number
     *
     * @param $fileType
     * @param $fileYear
     * @param $fileSemester
     * @param $classCampus
     * @param $classCode
     * @return int
     */
    public function getStaffMarkBookSequence($fileType, $fileYear, $fileSemester, $classCampus, $classCode) {
        $query = "SELECT COUNT(*)
                    FROM StaffAssessmentMarkBook
                    WHERE
                        FileType = '{$fileType}' AND
                        FileYear = '{$fileYear}' AND
                        FileSemester = '{$fileSemester}' AND
                        ClassCampus = '{$classCampus}' AND
                        ClassCode = '{$classCode}'
                 ";
        return $this->getDatabase()->getOne($query);
    }

    /**
     * Updates an existing markbook assessment
     *
     * @param string
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function updateAssessment($assessmentRemoteID, \Schoolbox\Assessment\Component\SubmissionBox $assessment)
    {
        $query = "UPDATE StaffAssessmentMarkBook SET
                        Heading = '".$this->getDatabase()->quote($assessment->getDescription())."',
                        HdgAbbrev1 = '".$this->getDatabase()->quote($assessment->getDescription())."',
                  WHERE
                        HdgAbbrev2 = '".$assessment->getId()."'
                    ";
        $this->getDatabase()->query($query);
    }

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param string
     * @param \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork
     * @return bool
     */
    public function setMark($assessmentRemoteID, \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork)
    {
        $assessmentRemote = $this->getAssessment($returnedWork->getSubmissionBox());

        $query = "INSERT INTO StudentAssessmentMarkBook (
                          FileType
                          FileYear,
                          FileSemester,
                          StaffID,
                          ClassCampus,
                          ClassCode,
                          ID,
                          MarkBookSeq,
                          Result,
                          TopicComment,
                          StudentAssessmentMarkBookSeq,
                          ResultDate,
                          ModifiedByID,
                          ModifiedDate
                          ) VALUES (
                            '{$assessmentRemote['FileType']}',
                            '{$assessmentRemote['FileYear']}',
                            '{$assessmentRemote['FileSemester']}',
                            ".$returnedWork->getMarker()->getExternalId().",
                            '{$assessmentRemote['ClassCampus']}',
                            '{$assessmentRemote['ClassCode']}',
                            ".$returnedWork->getOwner()->getExternalId().",
                            '{$assessmentRemote['MarkBookSeq']}',,
                            '".$returnedWork->getNormalizedMark()."',
                            '".$returnedWork->getComment()."',
                            CURDATE(),
                            ".$returnedWork->getMarker()->getExternalId().",
                            CURDATE()
                          )
      ";

        return $this->getDatabase()->query($query);
    }

    /**
     * @param MSSQL $database
     */
    public function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param \External_User $externalUser
     */
    public function setExternalUser($externalUser)
    {
        $this->externalUser = $externalUser;
    }

    /**
     * @return \External_User
     */
    public function getExternalUser()
    {
        return $this->externalUser;
    }


} 