<?php
/**
 * Synergetic Markbook Connector
 *
 * TODO: Using HdgAbbrev2 is stupid- this can be modified by users in Synergetic.
 * TODO: This should be modified to use MarkBookSeq/StaffAssessmentMarkbookSeq/StudentAssessmentMarkbookSeq
 *       (which is immutable).
 *
 * @stolenfrom  James Leckie <jleckie@alaress.com.au>
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
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
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\UserMapper;

class External_Synergetic_Markbook implements External_MarkbookInterface
{
    use External_FileYearSemesterTrait;
    use External_MssqlConnector;
    use External_Synergetic_ValidateExternalIdTrait;

    /** @var External_User */
    private $externalUser;

    // These prefixes are prefixed on the "markbook abbeviated heading 2"
    // to signify which table the ID which follows it relate to

    const ASSESSMENT_PREFIX = 'Q-';
    const SUBMISSION_PREFIX = 'W-';

    /** @const int NO_MARK_GIVEN Magic number to represent 'No Mark' when nulls not permitted */
    const NO_MARK_GIVEN = -1;

    /**
     * @const Character encoding for fields within this database.
     *        Required when converting field values from Schoolbox to Synergetic.
     */
    const CHARACTER_ENCODING = 'ASCII';

    /**
     * Constructor
     */
    public function __construct()
    {
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable

        $this->setupDatabase();

        $this->externalUser = new External_User;

        $this->setFileYear($this->externalUser->getCurrentYear());
        $this->setFileSemester($this->externalUser->getCurrentSemester());
    }

    /**
     * Returns the assessment information if found in remote system
     *
     * @param Activity $assessment
     * @return array
     */
    public function getAssessment(Activity $assessment)
    {
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
            WHERE     HdgAbbrev2 = :markbookIdentifier AND
                      FileYear = :fileYear AND    
                      FileSemester = :fileSemester
        ";

        return $this->getConnector()->executeQuery($sql, [
            'markbookIdentifier' => $this->getMarkbookIdentifier($assessment),
            'fileYear' => $this->fileYear,
            'fileSemester' => $this->fileSemester
        ])->fetchAll();
    }

    /**
     * Returns the string used to identify this assessment in the markbook.
     *
     * @param Activity $assessment
     * @return string
     */
    private function getMarkbookIdentifier(Activity $assessment)
    {
        // This column is limited to 10 characters
        $id = ($assessment->isAssessment() ? self::ASSESSMENT_PREFIX : self::SUBMISSION_PREFIX) . $assessment->getId();
        return $this->truncateForDatabase($id, 10);
    }

    /**
     * @inheritdoc
     */
    public function hasAssessment(Activity $assessment)
    {
        $assessmentFieldValues = $this->getAssessmentFieldValues($assessment);

        $sql = "
            SELECT    COUNT(*) AS result 
            FROM      StaffAssessmentMarkBook 
            WHERE     HdgAbbrev2 = :markbookIdentifier AND    
                      FileYear = :fileYear AND    
                      FileSemester = :fileSemester
        ";

        $statement = $this->getConnector()->executeQuery($sql, [
            'markbookIdentifier' => $assessmentFieldValues["HdgAbbrev2"],
            'fileYear' => $this->fileYear,
            'fileSemester' => $this->fileSemester
        ]);

        return $statement->fetchColumn() > 0;
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

        // Hydrate result type

        $resultType = $this->getResultType($assessment);

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
                    'fileYear' => $this->fileYear,
                    'fileSemester' => $this->fileSemester,
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
        $resultType = $this->getResultType($assessment);

        $sql = "
            UPDATE  StaffAssessmentMarkBook 
            SET     Heading = :heading,
                    HdgAbbrev1 = :headingAbbreviation1,
                    WeightingFactor = :weightingFactor,
                    ResultType = :resultType
            WHERE   HdgAbbrev2 = :headingAbbreviation2 AND 
                    FileYear = :fileYear AND  
                    FileSemester = :fileSemester
        ";

        return $this->getConnector()->prepareQuery($sql, [
            'heading' => $assessmentFieldValues["Heading"],
            'headingAbbreviation1' => $assessmentFieldValues["HdgAbbrev1"],
            'headingAbbreviation2' => $assessmentFieldValues["HdgAbbrev2"],
            'weightingFactor' => $assessmentFieldValues["WeightingFactor"],
            'resultType' => $resultType,
            'fileYear' => $this->fileYear,
            'fileSemester' => $this->fileSemester
        ])->execute();
    }

    /**
     * @inheritdoc
     */
    public function deleteAssessment(Activity $assessment)
    {
        // Delete results from the markbook

        $sql = "
            DELETE      studentBook
            FROM        StudentAssessmentMarkBook studentBook
            INNER JOIN  StaffAssessmentMarkBook staffBook ON studentBook.MarkBookSeq  = staffBook.MarkBookSeq AND
                        studentBook.FileType = staffBook.FileType AND
                        studentBook.FileYear = staffBook.FileYear AND
                        studentBook.FileSemester = staffBook.FileSemester AND
                        studentBook.ClassCampus = staffBook.ClassCampus AND
                        studentBook.ClassCode = staffBook.ClassCode
            WHERE       staffBook.HdgAbbrev2 = :headingAbbreviation2 AND 
                        studentBook.FileYear = :fileYear AND
                        studentBook.FileSemester = :fileSemester
        ";

        $this->getConnector()->executeQuery($sql, [
            'headingAbbreviation2' => $this->getMarkbookIdentifier($assessment),
            'fileYear' => $this->fileYear,
            'fileSemester' => $this->fileSemester
        ]);

        // Delete the markbook

        $sql = "
            DELETE      staffBook
            FROM        StaffAssessmentMarkBook staffBook
            WHERE       staffBook.HdgAbbrev2 = :headingAbbreviation2 AND
                        FileYear = :fileYear AND
                        FileSemester = :fileSemester
        ";

        $this->getConnector()->executeQuery($sql, [
            'headingAbbreviation2' => $this->getMarkbookIdentifier($assessment),
            'fileYear' => $this->fileYear,
            'fileSemester' => $this->fileSemester
        ]);
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

        // DB-level limits:
        // Heading: 255 chars
        // HdgAbbrev1, HdgAbbrev2: 10 chars
        // WeightingFactor: 0-9999
        return [
            'Heading'         => $this->truncateForDatabase($assessment->getName(), 255),
            'HdgAbbrev1'      => $this->truncateForDatabase($assessment->getName(), 10),
            'HdgAbbrev2'      => $this->getMarkbookIdentifier($assessment),
            'WeightingFactor' => max(0, min($weight, 9999)),
        ];
    }

    /**
     * @inheritdoc
     *
     * Synergetic does not permit null or empty string '' to be inserted in `StudentAssessmentMarkBook`.`Result`.
     * However, we still want to sync a comment from SB -> Synergetic when it is published.
     * In this case, we enter a -1 to represent 'No mark' in Synergetic
     *
     * @param ReturnedWork|QuizAttempt $feedback
     * @return bool
     */
    public function setMark($feedback)
    {
        $mark = null;

        if ($feedback instanceof ReturnedWork) {
            // Handle submission box result
            $box      = SubmissionBoxMapper::getInstance()->findById($feedback->getSubmissionBox()->getId());
            $activity = $this->buildActivityFromSubmissionBox($box);
            $marker   = UserMapper::getInstance()->findById($feedback->getMarker()->getId());
            $student  = UserMapper::getInstance()->findById($feedback->getOwner()->getId());

            // If the mark type is set to none then we shouldn't be saving a normalised mark
            if ($box->getMarkType()->getSlug() !== SubmissionBox\MarkType::SLUG_NONE) {
                $mark = $feedback->getNormalizedMark();
            }
            $comment = $feedback->getComment();

        } elseif ($feedback instanceof QuizAttempt) {
            // Handle quiz attempt
            $quiz     = QuizMapper::getInstance()->findById($feedback->getQuiz()->getId());
            $activity = $this->buildActivityFromQuiz($quiz);
            $student  = UserMapper::getInstance()->findById($feedback->getUser()->getId());
            $marker   = $activity->getAssessment()->getUser();

            // TODO: Some old handler action needs to be deprecated
            $evAttempt = new Assess_Attempt();
            $total = (float)$evAttempt->getTotalMarks($feedback->getQuiz()->getId(), $feedback->getId());
            $actual = (float)$evAttempt->getMarksForAttempt($feedback->getId());
            if($total > 0) {
                $mark = ($actual / $total) * 100;
            }
            else {
                $mark = 0;
            }
            $comment = '';

        } else {

            throw new Exception('Unknown object passed');
        }

        if (!(
            $this->isExternalIdValid($student->getExternalId())
            && $this->isExternalIdValid($marker->getExternalId())
        )) {
            return false;
        }

        return $this->setActivityMark($activity, $marker, $student, $mark, $comment);
    }



    /**
     * Determines if a student has already been marked or not, used to determining if to use insert or update
     *
     * @param $fileType
     * @param $classCampus
     * @param $classCode
     * @param $assessmentID
     * @param $studentID
     * @return bool
     */
    private function doesMarkExist($fileType, $classCampus, $classCode, $assessmentID, $studentID)
    {
        if (!$this->isExternalIdValid($studentID)) {
            return false;
        }

        $bindings = [
            'fileType' => $fileType,
            'fileYear' => $this->fileYear,
            'fileSemester' => $this->fileSemester,
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

        return (bool)$this->getConnector()->executeQuery($sql, $bindings)->fetchColumn();
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


    /**
     * @param SubmissionBox $submissionBox
     * @return Activity
     */
    private function buildActivityFromSubmissionBox(SubmissionBox $submissionBox)
    {
        $activity = new Activity();
        $activity->setSubmissionBox($submissionBox)
                 ->setOwner($submissionBox->getOwner())
                 ->setCategory($submissionBox->getCategory())
                 ->setDate($submissionBox->getDueDate());
        return $activity;
    }

    /**
     * @param Quiz $quiz
     * @return Activity
     */
    private function buildActivityFromQuiz(Quiz $quiz)
    {
        $activity = new Activity();
        $activity->setAssessment($quiz)
                 ->setOwner($quiz->getUser())
                 ->setCategory($quiz->getCategory())
                 ->setDate($quiz->getFinish());
        return $activity;
    }


    /**
     * Inserts/Updates a student's mark and comment in Synergetic
     *
     * @param Activity        $activity
     * @param \Schoolbox\User $marker
     * @param \Schoolbox\User $student
     * @param null|float      $mark
     * @param string          $comment
     * @return bool
     */
    private function setActivityMark(Activity $activity, Schoolbox\User $marker, Schoolbox\User $student, $mark, $comment)
    {
        $assessmentRemoteList = $this->getAssessment($activity);

        // If assessment box does not exist, create it first
        if (!is_array($assessmentRemoteList) || count($assessmentRemoteList) < 1) {
            // In this context, we need to ensure the fileYear etc match the student, not the marker
            $activity->setOwner($student);
            $this->createAssessment($activity);
            $assessmentRemoteList = $this->getAssessment($activity);
        }

        $result = true;

        /* Ensure we cater for 0 which coerces to 'false'.
         * If no mark, set the insert value to a magic number.
         */
        $markValue = null === $mark
            ? self::NO_MARK_GIVEN
            : round($mark);

        $hasMarkOrComment = (null !== $mark) || ('' !== trim($comment));

        foreach ($assessmentRemoteList as $assessmentRemote) {
            // Does the student have already have a mark for the assessment in Synergetic?
            $remoteMarkExists = $this->doesMarkExist(
                $assessmentRemote['FileType'],
                $assessmentRemote['ClassCampus'],
                $assessmentRemote['ClassCode'],
                $assessmentRemote['MarkBookSeq'],
                $student->getExternalId()
            );

            if (!$remoteMarkExists && $hasMarkOrComment) {
                // No mark in synergetic, mark/comment in SB
                $result = $this->insertActivityMark(
                    $activity, $assessmentRemote, $marker, $student, $markValue, $comment
                );
            } elseif ($hasMarkOrComment) {
                // Mark/Comment in synergetic + SB
                $result = $this->updateActivityMark($assessmentRemote, $student, $markValue, $comment);
            } elseif ($remoteMarkExists) {
                // Mark/Comment in synergetic , none in SB
                $result = $this->deleteActivityMark($assessmentRemote, $student);
            }
        }
        return $result;
    }


    /**
     * Update a student's existing mark/comment in synergetic
     *
     * @param array           $assessmentRemote
     * @param \Schoolbox\User $student
     * @param                 $markValue
     * @param                 $comment
     * @return bool
     */
    private function updateActivityMark(
        array $assessmentRemote,
        Schoolbox\User $student,
        $markValue,
        $comment
    ) {
        $sql = '
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
        ';
        $bindings = [
            'mark'         => $markValue,
            'topicComment' => $comment,
            'fileType'     => $assessmentRemote['FileType'],
            'fileYear'     => $this->fileYear,
            'fileSemester' => $this->fileSemester,
            'classCampus'  => $assessmentRemote['ClassCampus'],
            'classCode'    => $assessmentRemote['ClassCode'],
            'ID'           => $student->getExternalId(),
            'markbookSeq'  => $assessmentRemote['MarkBookSeq'],
        ];
        $statement = $this->getConnector()->prepareQuery($sql, $bindings);
        return $statement->execute();
    }


    /**
     * Delete a student's mark/comment from synergetic
     *
     * @param array           $assessmentRemote
     * @param \Schoolbox\User $student
     * @return bool
     */
    private function deleteActivityMark(array $assessmentRemote, Schoolbox\User $student)
    {
        $sql = '
            DELETE FROM StudentAssessmentMarkBook
            WHERE       FileType = :fileType AND
                        FileYear = :fileYear AND
                        FileSemester = :fileSemester AND
                        ClassCampus = :classCampus AND
                        ClassCode = :classCode AND
                        ID = :ID AND
                        MarkBookSeq = :markbookSeq
        ';
        $bindings = [
            'fileType'     => $assessmentRemote['FileType'],
            'fileYear'     => $this->fileYear,
            'fileSemester' => $this->fileSemester,
            'classCampus'  => $assessmentRemote['ClassCampus'],
            'classCode'    => $assessmentRemote['ClassCode'],
            'ID'           => $student->getExternalId(),
            'markbookSeq'  => $assessmentRemote['MarkBookSeq'],
        ];
        $statement = $this->getConnector()->prepareQuery($sql, $bindings);
        return $statement->execute();
    }


    /**
     * Insert a student's mark/comment into Synergetic
     *
     * @param Activity        $activity
     * @param array           $assessmentRemote
     * @param \Schoolbox\User $marker
     * @param \Schoolbox\User $student
     * @param int             $markValue
     * @param string          $comment
     * @return bool
     */
    private function insertActivityMark(
        Activity $activity,
        array $assessmentRemote,
        Schoolbox\User $marker,
        Schoolbox\User $student,
        $markValue,
        $comment
    ) {
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
        $bindings = [
            'fileType'         => $assessmentRemote['FileType'],
            'fileYear'         => $this->fileYear,
            'fileSemester'     => $this->fileSemester,
            'classCampus'      => $assessmentRemote['ClassCampus'],
            'classCode'        => $assessmentRemote['ClassCode'],
            'externalID'       => $student->getExternalId(),
            'markbookSeq'      => $assessmentRemote['MarkBookSeq'],
            'mark'             => $markValue,
            'topicComment'     => $comment,
            'resultDate'       => $activity->getDate()->getDatabase(),
            'markerExternalId' => $marker->getExternalId(),
        ];
        $statement = $this->getConnector()->prepareQuery($sql, $bindings);
        return $statement->execute();
    }

    /**
     * Determine what result type this assessment should have.
     *
     * @param Activity $assessment
     * @return string
     */
    private function getResultType(Activity $assessment)
    {
        $defaultType = 'OGRaw';

        // Does this assessment have a work type with an external result type?
        if (!empty($assessment->getType())) {
            $workType   = WorkTypeMapper::getInstance()->findById($assessment->getType());
            $resultType = $workType->getExternalResultType();
        }

        // We know OGRaw is in the DB, and if it's not there's bigger problems
        if (empty($resultType)) {
            return $defaultType;
        }

        // Validate the result type is actually in the DB
        $sql = "
            SELECT COUNT(*)
            FROM   luReportResultType
            WHERE  Code = :resultType
        ";
        $count = $this->getConnector()->executeQuery($sql, ['resultType' => $resultType,])->fetchColumn();
        if ($count > 0) {
            return $resultType;
        }
        return $defaultType;
    }

    /**
     * Performs truncation required for database inserts.
     *
     * No encoding conversion is required: the database connection is able to
     * save characters if they're part of whatever the column's code page is,
     * otherwise it just saves a ? (which unrecognised chars convert to anyway)
     *
     * @param string $string
     * @param int    $length
     * @return string
     */
    private function truncateForDatabase($string, $length)
    {
        return mb_substr($string, 0, $length, self::CHARACTER_ENCODING);
    }
}
