<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\UserMapper;

class External_Synergetic_Markbook implements External_MarkbookInterface
{
    use External_FileYearSemesterTrait;
    use External_MssqlConnector;
    use External_Synergetic_ValidateExternalIdTrait;

    /** @var External_User */
    private $externalUser;

    // These prefixes are prefixed on the "markbook abbeviated heading 2" to signify which table the ID which follows it relate to

    // TODO: These sort of shenanigans will screw us- we should stop being lazy and track these links back to the immutable MarkbookSeq internally in Schoolbox

    const ASSESSMENT_PREFIX = 'Q-';
    const SUBMISSION_PREFIX = 'W-';

    /**
     * Constructor
     */
    public function __construct()
    {
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable

        $this->setupDatabase();
        $this->setExternalUser(new External_User);
    }

    /**
     * Returns the assessment information if found in remote system
     *
     * @param Activity $assessment
     * @param int|null $year
     * @param int|null $semester
     * @return array
     */
    public function getAssessment(Activity $assessment)
    {
        $bindings = [
            'markbookIdentifier' => $this->getMarkbookIdentifier($assessment)
        ];

        // TODO: Using HdgAbbrev2 is stupid- this can be modified by users in Synergetic. This should be modified to use MarkBookSeq which is immutable.

        $sql = "
            SELECT    FileType,
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
            FROM      StaffAssessmentMarkBook
            WHERE     HdgAbbrev2 = :markbookIdentifier
        ";

        if (!empty($year)) {
            $sql .= " AND FileYear = :fileYear";
            $bindings['fileYear'] = $year;
        }

        if (!empty($semester)) {
            $sql .= " AND FileSemester = :fileSemester";
            $bindings['fileSemester'] = $semester;
        }

        $statement = $this->getConnector()->executeQuery($sql, $bindings);
        return $statement->fetchAll();
    }

    /**
     * Returns the string used to identify this assessment in the markbook.
     *
     * @param Activity $assessment
     * @return string
     */
    private function getMarkbookIdentifier(Activity $assessment)
    {
        // TODO: These sort of shenanigans can screw us- we should stop being lazy and track these links back to the immutable MarkbookSeq internally in Schoolbox

        return ($assessment->isAssessment() ? self::ASSESSMENT_PREFIX : self::SUBMISSION_PREFIX) . $assessment->getId();
    }

    /**
     * @inheritdoc
     */
    public function createOrUpdateAssessment(Activity $assessment)
    {
        $assessmentFieldValues = $this->getAssessmentFieldValues($assessment);

        $bindings = [
            'markbookIdentifier' => $assessmentFieldValues["HdgAbbrev2"]
        ];

        $sql = "
            SELECT    COUNT(*) AS result 
            FROM      StaffAssessmentMarkBook 
            WHERE     HdgAbbrev2 = :markbookIdentifier
        ";

        if (!empty($year)) {
            $sql .= " AND FileYear = :fileYear";
            $bindings['fileYear'] = $year;
        }

        if (!empty($semester)) {
            $sql .= " AND FileSemester = :fileSemester";
            $bindings['fileSemester'] = $semester;
        }

        $statement = $this->getConnector()->executeQuery($sql, $bindings);

        // If the markbook already exists, do an update

        if ($statement->fetch()) {
            return $this->updateAssessment($assessment, $year, $semester);
        }

        // Otherwise create only

        return $this->createAssessment($assessment, $year, $semester);
    }

    /**
     * @inheritdoc
     */
    public function createAssessment(Activity $assessment)
    {
        /** @var bool $result */
        $result = false;

        /** Fix the hydrations */
        $folder = $assessment->getCategory();

        /** @var int $folderId */
        $folderId = $folder instanceof Folder ? $folder->getId() : $folder;
        $folder = FolderMapper::getInstance()->findById($folderId);

        $externalClass = new External_Synergetic_Class();

        if ($assessment->getOwner() instanceof Schoolbox\User) {
            $owner = $assessment->getOwner();
        } else {
            $owner = UserMapper::getInstance()->findById($assessment->getOwner());
        }

        if (!$this->isExternalIdValid($owner->getExternalId())) {
            return false;
        }

        // If file year/semester were supplied, used them- otherwise go for the defaults

        if (!empty($year)) {
            $fileYear = $year;
        } else {
            $fileYear = $this->getExternalUser()->getCurrentYear($owner->getExternalId());
        }

        if (!empty($semester)) {
            $fileSemester = $semester;
        } else {
            $fileSemester = $this->getExternalUser()->getCurrentSemester($owner->getExternalId());
        }

        // Hydrate result type

        $workType   = WorkTypeMapper::getInstance()->findById($assessment->getType());
        $resultType = $workType->getExternalResultType();

        if (empty($resultType)) {
            $resultType = 'OGRaw';
        }

        /** @var FolderCode $classCode */
        foreach ($folder->getCodes() as $classCode) {

            $class = $externalClass->getClass($classCode->getCode(), $owner);

            if (!empty($class)) {
                $staffID = $class['MultipleStaffMarkbookFlag'] > 0 ? $owner->getExternalId() : 0;

                $assessmentFieldValues = $this->getAssessmentFieldValues($assessment);

                $sql = "
                    INSERT INTO StaffAssessmentMarkBook (
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
                        :fileType,
                        :fileYear,
                        :fileSemester,
                        :staffID,
                        :classCampus,
                        :classCode,
                        '',
                        :heading,
                        :headingAbbreviation1,
                        :headingAbbreviation2,
                        :resultType,
                        '100',
                        :weightingFactor
                    )
                ";

                $statement = $this->getConnector()->prepareQuery($sql, [
                    'fileType' => $class['FileType'],
                    'fileYear' => $fileYear,
                    'fileSemester' => $fileSemester,
                    'staffID' => $staffID,
                    'classCampus' => $class['ClassCampus'],
                    'classCode' => $class['ClassCode'],
                    'heading' => $assessmentFieldValues["Heading"],
                    'headingAbbreviation1' => $assessmentFieldValues["HdgAbbrev1"],
                    'headingAbbreviation2' => $assessmentFieldValues["HdgAbbrev2"],
                    'resultType' => $resultType,
                    'weightingFactor' => $assessmentFieldValues["WeightingFactor"]
                ]);

                $result = $statement->execute();
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function updateAssessment(Activity $assessment)
    {
        $assessmentFieldValues = $this->getAssessmentFieldValues($assessment);

        $bindings = [
            'heading' => $assessmentFieldValues["Heading"],
            'headingAbbreviation1' => $assessmentFieldValues["HdgAbbrev1"],
            'headingAbbreviation2' => $assessmentFieldValues["HdgAbbrev2"],
            'weightingFactor' => $assessmentFieldValues["WeightingFactor"]
        ];

        $sql = "
            UPDATE  StaffAssessmentMarkBook 
            SET     Heading = :heading,
                    HdgAbbrev1 = :headingAbbreviation1,
                    WeightingFactor = :weightingFactor
            WHERE   HdgAbbrev2 = :headingAbbreviation2
        ";

        if (!empty($year)) {
            $sql .= " AND FileYear = :fileYear";
            $bindings['fileYear'] = $year;
        }

        if (!empty($semester)) {
            $sql .= " AND FileSemester = :fileSemester";
            $bindings['fileSemester'] = $semester;
        }

        $statement = $this->getConnector()->prepareQuery($sql, $bindings);

        return $statement->execute();
    }

    /**
     * @inheritdoc
     */
    public function deleteAssessment(Activity $assessment)
    {
        $bindings = [
            'headingAbbreviation2' => $this->getMarkbookIdentifier($assessment),
        ];

        $sql = "
            DELETE studentBook
            FROM StudentAssessmentMarkBook studentBook
            INNER JOIN StaffAssessmentMarkBook staffBook
                ON  studentBook.MarkBookSeq  = staffBook.MarkBookSeq
                AND studentBook.FileType     = staffBook.FileType
                AND studentBook.FileYear     = staffBook.FileYear
                AND studentBook.FileSemester = staffBook.FileSemester
                AND studentBook.ClassCampus  = staffBook.ClassCampus
                AND studentBook.ClassCode    = staffBook.ClassCode
            WHERE staffBook.HdgAbbrev2 = :headingAbbreviation2
        ";

        if (!empty($year)) {
            $sql .= " AND FileYear = :fileYear";
            $bindings['fileYear'] = $year;
        }

        if (!empty($semester)) {
            $sql .= " AND FileSemester = :fileSemester";
            $bindings['fileSemester'] = $semester;
        }

        $this->getConnector()->executeQuery($sql, $bindings);

        $bindings = [
            'headingAbbreviation2' => $this->getMarkbookIdentifier($assessment),
        ];

        $sql = "
            DELETE staffBook
            FROM StaffAssessmentMarkBook staffBook
            WHERE staffBook.HdgAbbrev2 = :headingAbbreviation2
        ";

        $this->getConnector()->executeQuery($sql, $bindings);
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
        } elseif ($assessment->getAssessment() instanceof Quiz) {
            $weight = $assessment->getAssessment()->getWeight();
        }

        return [
            'Heading' => $assessment->getName(),
            'HdgAbbrev1' => mb_substr($assessment->getName(), 0, 10, 'UTF-8'),
            'HdgAbbrev2' => $this->getMarkbookIdentifier($assessment),
            'WeightingFactor' => $weight,
        ];
    }

    /**
     * @inheritdoc
     */
    public function setMark($result)
    {
        $activity = new Activity;

        if ($result instanceof ReturnedWork) {

            // Handle submission box result

            // TODO: Someone with a better understanding of learning needs to kill this deprecated function call

            $box = SubmissionBoxMapper::getInstance()->findById($result->getSubmissionBox()->getId());
            $activity->setSubmissionBox($box);
            $activity->setCategory($box->getCategory());
            $marker = UserMapper::getInstance()->findById($result->getMarker()->getId());
            $student = UserMapper::getInstance()->findById($result->getOwner()->getId());

            // If the mark type is set to none then we shouldn't be saving a normalised mark

            if ($box->getMarkType()->getId() != SubmissionBox\MarkType::TYPE_NONE) {
                $mark = $result->getNormalizedMark();
            } else {
                $mark = null;
            }

            $comment = $result->getComment();
            $dueDate = $box->getDueDate();

        } elseif ($result instanceof QuizAttempt) {

            // Handle quiz attempt

            $quiz = QuizMapper::getInstance()->findById($result->getQuiz()->getId());
            $activity->setAssessment($quiz);
            $activity->setCategory($quiz->getCategory());
            $student = UserMapper::getInstance()->findById($result->getUser()->getId());
            $marker = $activity->getAssessment()->getUser();

            // TODO: Some old handler action needs to be deprecated

            $evAttempt = new Assess_Attempt();
            $total = $evAttempt->getTotalMarks($result->getQuiz()->getId(), $result->getId());
            $actual = $evAttempt->getMarksForAttempt($result->getId());
            $mark = ($actual / $total) * 100;
            $comment = '';
            $dueDate = $quiz->getFinish();

        } else {

            throw new Exception('Unknown object passed');
        }

        $activity->setOwner($student);

        if (!($this->isExternalIdValid($student->getExternalId()) && $this->isExternalIdValid($marker->getExternalId()))) {
            return false;
        }

        $assessmentRemoteList = $this->getAssessment($activity);

        // If assessment box does not exist, create it first

        if (!is_array($assessmentRemoteList) || count($assessmentRemoteList) < 1) {
            $this->createAssessment($activity);
            $assessmentRemoteList = $this->getAssessment($activity);
        }

        $result = true;

        foreach ($assessmentRemoteList as $assessmentRemote) {

            if (!empty($year)) {
                $fileYear = $year;
            } else {
                $fileYear = $assessmentRemote['FileYear'];
            }

            if (!empty($semester)) {
                $fileSemester = $semester;
            } else {
                $fileSemester = $assessmentRemote['FileSemester'];
            }

            if (isset($mark) && is_numeric($mark)) {
                $mark = round($mark);
            }

            if ($this->doesMarkExist($assessmentRemote['FileType'],
                    $assessmentRemote['FileYear'],
                    $assessmentRemote['FileSemester'],
                    $assessmentRemote['ClassCampus'],
                    $assessmentRemote['ClassCode'],
                    $assessmentRemote['MarkBookSeq'],
                    $student->getExternalId())
            ) {

                if (isset($mark) || !empty($comment)) {

                    $bindings = [
                        'mark' => $mark,
                        'topicComment' => $comment,
                        'fileType' => $assessmentRemote['FileType'],
                        'fileYear' => $fileYear,
                        'fileSemester' => $fileSemester,
                        'classCampus' => $assessmentRemote['ClassCampus'],
                        'classCode' => $assessmentRemote['ClassCode'],
                        'ID' => $student->getExternalId(),
                        'markbookSeq' => $assessmentRemote['MarkBookSeq'],
                    ];

                    $sql = "
                        UPDATE  StudentAssessmentMarkBook 
                        SET     Result = :mark,
                                TopicComment = :topicComment
                        WHERE   FileType = :fileType AND
                                FileYear = :fileYear AND
                                FileSemester = :fileSemester AND
                                ClassCampus = :classCampus AND
                                ClassCode = :classCode AND
                                ID = :ID AND
                                MarkBookSeq = :markbookSeq
                    ";

                } else {

                    $bindings = [
                        'fileType' => $assessmentRemote['FileType'],
                        'fileYear' => $fileYear,
                        'fileSemester' => $fileSemester,
                        'classCampus' => $assessmentRemote['ClassCampus'],
                        'classCode' => $assessmentRemote['ClassCode'],
                        'ID' => $student->getExternalId(),
                        'markbookSeq' => $assessmentRemote['MarkBookSeq'],
                    ];

                    $sql = "
                        DELETE FROM StudentAssessmentMarkBook
                        WHERE       FileType = :fileType AND
                                    FileYear = :fileYear AND
                                    FileSemester = :fileSemester AND
                                    ClassCampus = :classCampus AND
                                    ClassCode = :classCode AND
                                    ID = :ID AND
                                    MarkBookSeq = :markbookSeq
                    ";

                    if (!empty($year)) {
                        $sql .= " AND FileYear = :fileYear";
                        $bindings['fileYear'] = $year;
                    }

                    if (!empty($semester)) {
                        $sql .= " AND FileSemester = :fileSemester";
                        $bindings['fileSemester'] = $semester;
                    }
                }

            } else if (isset($mark) || !empty($comment)) {

                $bindings = [
                    'fileType' => $assessmentRemote['FileType'],
                    'fileYear' => $fileYear,
                    'fileSemester' => $fileSemester,
                    'classCampus' => $assessmentRemote['ClassCampus'],
                    'classCode' => $assessmentRemote['ClassCode'],
                    'externalID' => $student->getExternalId(),
                    'markbookSeq' => $assessmentRemote['MarkBookSeq'],
                    'mark' => $mark,
                    'topicComment' => $comment,
                    'resultDate' => $dueDate->getDatabase(),
                    'markerExternalId' => $marker->getExternalId()
                ];

                $sql = "
                    INSERT INTO StudentAssessmentMarkBook (
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
                        :fileType,
                        :fileYear,
                        :fileSemester,
                        :classCampus,
                        :classCode,
                        :externalID,
                        '0',
                        :markbookSeq,
                        :mark,
                        :topicComment,
                        :resultDate,
                        :markerExternalId,
                        CURRENT_TIMESTAMP
                    )
                ";
            }

            if (isset($sql) && isset($bindings)) {
                $statement = $this->getConnector()->prepareQuery($sql, $bindings);
                $result = $statement->execute();
            }
        }

        return $result;
    }

    /**
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
    private function doesMarkExist($fileType, $fileYear, $fileSemester, $classCampus, $classCode, $assessmentID, $studentID)
    {
        if (!$this->isExternalIdValid($studentID)) {
            return false;
        }

        $bindings = [
            'fileType' => $fileType,
            'fileYear' => $fileYear,
            'fileSemester' => $fileSemester,
            'classCampus' => $classCampus,
            'classCode' => $classCode,
            'markbookSeq' => $assessmentID,
            'studentID' => $studentID
        ];

        $sql = "
            SELECT  COUNT(*) AS result
            FROM    StudentAssessmentMarkBook
            WHERE   FileType = :fileType AND
                    FileYear = :fileYear AND
                    FileSemester = :fileSemester AND
                    ClassCampus = :classCampus AND
                    ClassCode = :classCode AND
                    MarkBookSeq = :markbookSeq AND
                    ID = :studentID
        ";

        $query = $this->getConnector()->executeQuery($sql, $bindings);
        return $query->fetchColumn();
    }

    /**
     * @return External_User
     */
    public function getExternalUser()
    {
        return $this->externalUser;
    }

    /**
     * @param External_User $externalUser
     * @return $this
     */
    public function setExternalUser($externalUser)
    {
        $this->externalUser = $externalUser;
        return $this;
    }
}
