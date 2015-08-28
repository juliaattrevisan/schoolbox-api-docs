<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

use \Schoolbox\Assessment\Component\SubmissionBox;
use \Schoolbox\Assessment\Activity;
use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use \Schoolbox\Assessment\Component\SubmissionBoxMapper;
use \Schoolbox\Assessment\Component\OnlineAssessment\Attempt;
use \Schoolbox\UserMapper;
use Alaress\DebugBar\Instance as DebugBar;

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

    const ASSESSMENT_PREFIX = 'Q-';
    const SUBMISSION_PREFIX = 'W-';

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
     * @param Activity $assessment
     * @return array
     */
    public function getAssessment(Activity $assessment)
    {
        $query = "SELECT FileType,
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
                        MarkOutOf,
                        WeightingFactor
                  FROM StaffAssessmentMarkBook
                  WHERE HdgAbbrev2 = '".$this->getMarkbookIdentifier($assessment)."'";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * Returns the string used to identify this assessment in the markbook.
     *
     * @param Activity $assessment
     * @return string
     */
    private function getMarkbookIdentifier(Activity $assessment)
    {
        return ($assessment->isAssessment() ? self::ASSESSMENT_PREFIX : self::SUBMISSION_PREFIX) . $assessment->getId();
    }

    /**
     * Creates a new assessment item in the markbook
     *
     * @param \Schoolbox\User
     * @param Activity $assessment
     * @return bool
     */
    public function createAssessment(Activity $assessment)
    {
        /** Fix the hydrations */
        if($assessment->getCategory() instanceof Schoolbox\Resource\Folder\Model) {
            $folder = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($assessment->getCategory()->getId());
        }
        else {
            $folder = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($assessment->getCategory());
        }

        $externalClass = new External_Synergetic_Class();
        if($assessment->getOwner() instanceof Schoolbox\User) {
            $owner = $assessment->getOwner();
        } else {
            $owner = UserMapper::getInstance()->findById($assessment->getOwner());
        }

        $fileYear = $this->getExternalUser()->getCurrentYear($owner->getExternalId());
        $fileSemester = $this->getExternalUser()->getCurrentSemester($owner->getExternalId());

        foreach($folder->getCodes() as $classCode) {

            $class = $externalClass->getClass($classCode->getCode());
            $staffID = $class['MultipleStaffMarkbookFlag'] > 0 ? $owner->getExternalId() : 0;

            $assessmentFieldValues = $this->getAssessmentFieldValues($assessment);
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
                        MarkOutOf,
                        WeightingFactor
                    ) VALUES (
                      '{$class['FileType']}',
                      '{$fileYear}',
                      '{$fileSemester}',
                      '{$staffID}',
                      '{$class['ClassCampus']}',
                      '{$class['ClassCode']}',
                      '',
                      '{$assessmentFieldValues["Heading"]}',
                      '{$assessmentFieldValues["HdgAbbrev1"]}',
                      '{$assessmentFieldValues["HdgAbbrev2"]}',
                      'OGRaw',
                      '100',
                      '{$assessmentFieldValues["WeightingFactor"]}'
                    )";
            $result = $this->getDatabase()->query($query);
        }
        return $result;
    }


    /**
     * Updates an existing markbook assessment
     *
     * @param string
     * @param Activity $assessment
     * @return array
     */
    public function updateAssessment(Activity $assessment)
    {
        $assessmentFieldValues = $this->getAssessmentFieldValues($assessment);
        $query = "UPDATE StaffAssessmentMarkBook SET
                        Heading = '{$assessmentFieldValues["Heading"]}',
                        HdgAbbrev1 = '{$assessmentFieldValues["HdgAbbrev1"]}',
                        WeightingFactor = '{$assessmentFieldValues["WeightingFactor"]}'
                  WHERE
                        HdgAbbrev2 = '{$assessmentFieldValues["HdgAbbrev2"]}'
                    ";
        $this->getDatabase()->query($query);
    }

    /**
     * Gets database field values out of an assessment. Gets the following fields:
     * * Heading
     * * HdgAbbrev1
     * * HdgAbbrev2 (used as the identifier for this row)
     * * WeightingFactor
     *
     * @param Activity $assessment
     *
     * @return array
     */
    protected function getAssessmentFieldValues(Activity $assessment)
    {
        $weight = 0;
        if ($assessment->getSubmissionBox() instanceof Schoolbox\Assessment\Component\SubmissionBox) {
            $weight = $assessment->getSubmissionBox()->getWeight();
        } elseif($assessment->getAssessment() instanceof Schoolbox\Assessment\Component\OnlineAssessment) {
            $weight = $assessment->getAssessment()->getWeight();
        }
        return [
            'Heading'         => $this->getDatabase()->quote($assessment->getName()),
            'HdgAbbrev1'      => $this->getDatabase()->quote(TextFormatter::cutString($assessment->getName(),10)),
            'HdgAbbrev2'      => $this->getMarkbookIdentifier($assessment),
            'WeightingFactor' => $weight,
        ];
    }

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param Attempt|ReturnedWork $result
     * @return bool
     * @throws Exception
     */
    public function setMark( $result)
    {

        $activity = new Activity;

        if ($result instanceof ReturnedWork) {
            /* Handle submission box result */
            $box = SubmissionBoxMapper::getInstance()->findById($result->getSubmissionBox()->getId());
            $activity->setSubmissionBox($box);
            $activity->setCategory($box->getCategory());
            $marker = UserMapper::getInstance()->findById($result->getMarker());
            $student = UserMapper::getInstance()->findById($result->getOwner()->getId());
            $mark = $result->getNormalizedMark();
            $comment = $result->getComment();
        } elseif ($result instanceof Attempt) {
            /* Handle quiz attempt */
            $quiz = \Schoolbox\Assessment\Component\OnlineAssessmentMapper::getInstance()->findByID($result->getAssessment()->getId());
            $activity->setAssessment($quiz);
            $activity->setCategory($quiz->getCategory());
            $student = UserMapper::getInstance()->findById($result->getUser()->getId());
            $marker = $activity->getAssessment()->getUser();

            /* TODO Some old handler action needs to be deprecated */
            $evAttempt = new Assess_Attempt();
            $total = $evAttempt->getTotalMarks($result->getAssessment()->getId(), $result->getId());
            $actual = $evAttempt->getMarksForAttempt($result->getId());
            $mark = ($actual / $total)*100;
            $comment = '';
        }
        else {
            throw new \Exception('Unknown object passed');
        }
        $activity->setOwner($student);

        $assessmentRemoteList = $this->getAssessment($activity);

        // If assessment box does not exist, create it first
        if(!is_array($assessmentRemoteList) || count($assessmentRemoteList) < 1 ) {
            $this->createAssessment($activity);
            $assessmentRemoteList = $this->getAssessment($activity);
        }

        foreach($assessmentRemoteList as $assessmentRemote ) {

            if($this->doesMarkExist($assessmentRemote['FileType'],
                $assessmentRemote['FileYear'],
                $assessmentRemote['FileSemester'],
                $assessmentRemote['ClassCampus'],
                $assessmentRemote['ClassCode'],
                $assessmentRemote['MarkBookSeq'],
                $student->getExternalId())
            ) {
                if (isset($mark)) {
                    $query = "UPDATE StudentAssessmentMarkBook SET
                            Result = '".round($mark)."',
                            TopicComment = '".$this->getDatabase()->quote($comment)."'
                          WHERE
                              FileType = '{$assessmentRemote['FileType']}' AND
                              FileYear = '{$assessmentRemote['FileYear']}' AND
                              FileSemester = '{$assessmentRemote['FileSemester']}' AND
                              ClassCampus = '{$assessmentRemote['ClassCampus']}' AND
                              ClassCode = '{$assessmentRemote['ClassCode']}' AND
                              ID = '" . $student->getExternalId() . "' AND
                              MarkBookSeq = '{$assessmentRemote['MarkBookSeq']}'
                         ";
                } else {
                    $query = "DELETE FROM StudentAssessmentMarkBook
                          WHERE
                              FileType = '{$assessmentRemote['FileType']}' AND
                              FileYear = '{$assessmentRemote['FileYear']}' AND
                              FileSemester = '{$assessmentRemote['FileSemester']}' AND
                              ClassCampus = '{$assessmentRemote['ClassCampus']}' AND
                              ClassCode = '{$assessmentRemote['ClassCode']}' AND
                              ID = '" . $student->getExternalId() . "' AND
                              MarkBookSeq = '{$assessmentRemote['MarkBookSeq']}'
                         ";
                }
            } else {
                if (isset($mark)) {
                    $query = "INSERT INTO StudentAssessmentMarkBook (
                                  FileType,
                                  FileYear,
                                  FileSemester,
                                  ClassCampus,
                                  ClassCode,
                                  ID,
                                  StaffID,
                                  MarkBookSeq,
                                  Result,
                                  TopicComment,
                                  ResultDate,
                                  ModifiedByID,
                                  ModifiedDate
                                  ) VALUES (
                                    '{$assessmentRemote['FileType']}',
                                    '{$assessmentRemote['FileYear']}',
                                    '{$assessmentRemote['FileSemester']}',
                                    '{$assessmentRemote['ClassCampus']}',
                                    '{$assessmentRemote['ClassCode']}',
                                    '".$student->getExternalId()."',
                                    '0',
                                    '{$assessmentRemote['MarkBookSeq']}',
                                    '".round($mark)."',
                                    '".$this->getDatabase()->quote($comment)."',
                                    CURRENT_TIMESTAMP,
                                    '".$marker->getExternalId()."',
                                    CURRENT_TIMESTAMP
                                  )
                    ";
                }
            }

          $success = $this->getDatabase()->query($query);
        }
        return $success;
    }

    /**
     *
     * Determines if a student has already been marked or not, used to determining if to use insert or update
     *
     * @param $fileType
     * @param $fileYear
     * @param $fileSemester
     * @param $classCampus
     * @param $classCode
     * @param $assessmentID
     * @param $studentID
     * @return bool
     */
    private function doesMarkExist($fileType, $fileYear, $fileSemester, $classCampus, $classCode, $assessmentID, $studentID) {
        $query = "SELECT COUNT(*) as result
                    FROM StudentAssessmentMarkBook
                    WHERE
                        FileType = '{$fileType}' AND
                        FileYear = '{$fileYear}' AND
                        FileSemester = '{$fileSemester}' AND
                        ClassCampus = '{$classCampus}' AND
                        ClassCode = '{$classCode}' AND
                        MarkBookSeq = '{$assessmentID}' AND
                        ID = '{$studentID}'
                 ";
        $result = $this->getDatabase()->getRow($query);
        return $result['result'] > 0;
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