<?php
/**
 * @stolenfrom  James Leckie <jleckie@alaress.com.au>
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\UserMapper;
use Alaress\Database\Predicate;

/**
 * Synergetic Markbook Connector
 *
 * @todo: Using 'HdgAbbrev2' as a key is problematic - this can be modified by users in Synergetic.
 * @todo: This should be modified to use MarkBookSeq/StaffAssessmentMarkbookSeq/StudentAssessmentMarkbookSeq (which is immutable).
 *
 *      Note, this implies storing the relationship SB-side. Which would be desirable considering the code resides in the SB system.
 */
class External_Synergetic_Markbook implements External_MarkbookInterface
{

    use External_MssqlConnector;
    use External_Synergetic_ValidateExternalIdTrait;

    // These prefixes are prefixed on the "markbook abbreviated heading 2"
    // to signify which table the ID which follows it relate to
    private const ASSESSMENT_PREFIX = 'Q-';
    private const SUBMISSION_PREFIX = 'W-';

    /** @const int NO_MARK_GIVEN Magic number to represent 'No Mark' when nulls not permitted */
    private const NO_MARK_GIVEN = -1;

    /**
     * @const Character encoding for fields within this database.
     *        Required when converting field values from Schoolbox to Synergetic.
     */
    private const CHARACTER_ENCODING = 'UTF-8';

    /**
     * Must be used as the markbook's staff id if the markbook belongs to a folder code/year/semester combo that has/had multiple teachers.
     * A synergetic trigger exists to enforce this, eg:
     *
     * Global Staff ID (0) must not be used in a Multiple Staff Markbook Class (EN344)
     * Staff ID must not be used in a non-Multiple Staff Markbook Class
     */
    private const GLOBAL_STAFF_ID = 0;

    /** @var External_UserInterface */
    private $externalUser;

    /** @var int|null */
    private $fileYearOverride;

    /** @var int|null */
    private $fileSemesterOverride;


    /**
     * Constructor
     *
     * If $fileYearOverride and $fileSemesterOverride are provided, they will override both the
     * globally-configured synergetic file year/semester and also each student's file year/semester.
     *
     * @param int|null $fileYearOverride
     * @param int|null $fileSemesterOverride
     * @throws Exception
     * @throws InvalidArgumentException If instantiated with year or semester overridden but not the other
     * @todo There's a heap of dependencies in this class that are plucked from the ether. They should be constructor dependencies, injected by a factory.
     */
    public function __construct(int $fileYearOverride = null, int $fileSemesterOverride = null)
    {
        if (null === $fileYearOverride xor null === $fileSemesterOverride) {
            throw new InvalidArgumentException('You must override both year and semester, or neither');
        }
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable
        $this->setupDatabase();
        $this->externalUser         = new External_User();
        $this->fileYearOverride     = $fileYearOverride;
        $this->fileSemesterOverride = $fileSemesterOverride;
    }

    /**
     * Returns the assessment information if found in remote system
     *
     * Note this matches Synergetic's globally-configured year and semester,
     * rather than in the context of a specific user.
     *
     * @param Activity $activity
     * @return array
     */
    public function getAssessment(Activity $activity): array
    {
        return $this->getSynergeticMarkbookList($activity, $this->getSystemFileYear(), $this->getSystemFileSemester());
    }

    /**
     * Returns TRUE if there are any markbooks in synergetic for this assessment.
     *
     * Only checks Synergetic's current globally-configured year and semester, rather than in the context of a specific user.
     *
     * Moving an assessment to a new folder, or changing the assessment's folder's codes, break the link to synergetic's markbooks.
     *
     * IMPORTANT: A TRUE return value does not mean that Schoolbox and Synergetic are in sync regarding folders.
     *
     * Consider case where a folder has a single folder code, and an assessment is created. This will create a markbook in synergetic.
     * If the user adds another class code to the folder, this method will still return true, but we still need to CREATE the second markbook
     * in synergetic before we attempt to apply an update(). This extra logic is implemented in update().
     *
     * @inheritdoc
     */
    public function hasAssessment(Activity $activity): bool
    {
        $folderCodes = $activity->getCategory()->getCodes()->getPropertyValues('code');
        if (!\count($folderCodes)) {
            return false;
        }

        $folderCodeBindings    = Predicate\Builder::getBindings($folderCodes, 'code');
        $assessmentFieldValues = $this->getAssessmentFieldValues($activity);

        $sql = "
            SELECT    COUNT(*) AS result 
            FROM      StaffAssessmentMarkBook 
            WHERE     HdgAbbrev2 = :markbookIdentifier AND    
                      FileYear = :fileYear AND    
                      FileSemester = :fileSemester AND
                      ClassCode IN ({$folderCodeBindings->getPlaceholder()})
        ";

        $params = \array_merge([
            'markbookIdentifier' => $assessmentFieldValues["HdgAbbrev2"],
            'fileYear'           => $this->getSystemFileYear(),
            'fileSemester'       => $this->getSystemFileSemester(),
        ], $folderCodeBindings->getParams());

        $statement = $this->getConnector()->executeQuery($sql, $params);

        return $statement->fetchColumn() > 0;
    }


    /**
     * @inheritdoc
     *
     * If the activity belongs to a Schoolbox folder with multiple folder codes, this will
     * create a markbook in Synergetic per folder code.
     *
     * Creates the assessment in Synergetic's globally-configured year and semester, rather
     * than in the context of a specific user
     *
     * Returns true if ANY markbooks are created in Synergetic.
     *
     * @param Activity $assessment
     * @return bool
     * @throws \Alaress\Database\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function createAssessment(Activity $assessment): bool
    {
        $folder = $assessment->getCategory();
        $owner  = $assessment->getOwner();

        if (!$this->isExternalIdValid($owner->getExternalId())) {
            return false;
        }

        // Hydrate result type
        $resultType = $this->getResultType($assessment);
        $year       = $this->getSystemFileYear();
        $semester   = $this->getSystemFileSemester();

        $result = false;
        foreach ($folder->getCodes() as $code) {
            if ($this->insertStaffMarkbook($assessment, $owner, $code->getCode(), $resultType, $year, $semester)) {
                $result = true;
            }
        }

        return $result;
    }


    /**
     * May perform creations in synergetic, in addition to the update.
     *
     * Consider the case where a SB assessment resides on a folder with 2 folder codes, but only 1 markbook in synergetic.
     * $this->hasAssessment($assessment) will return TRUE, even though it is only a partial truth.
     *
     * @see $this->hasAssessment()
     * @inheritdoc
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Alaress\Database\Exception
     */
    public function updateAssessment(Activity $assessment): bool
    {
        $folderCodes = $assessment->getCategory()->getCodes()->getPropertyValues('code');
        if (!\count($folderCodes)) {
            return false;
        }

        $this->insertMarkbooksIfNotExists(
            $assessment,
            $assessment->getCategory()->getCodes()->getPropertyValues('code'),
            $this->getSystemFileYear(),
            $this->getSystemFileSemester()
        );

        $assessmentFieldValues = $this->getAssessmentFieldValues($assessment);
        $resultType            = $this->getResultType($assessment);
        $bindings              = Predicate\Builder::getBindings($folderCodes, 'code');

        $sql = "
            UPDATE  StaffAssessmentMarkBook 
            SET     Heading = :heading,
                    HdgAbbrev1 = :headingAbbreviation1,
                    WeightingFactor = :weightingFactor,
                    ResultType = :resultType,
                    TestDate = :dueDate
            WHERE   HdgAbbrev2 = :headingAbbreviation2 AND 
                    FileYear = :fileYear AND  
                    FileSemester = :fileSemester AND
                    ClassCode IN ({$bindings->getPlaceholder()})
        ";

        $params = \array_merge([
            'heading'              => $assessmentFieldValues["Heading"],
            'headingAbbreviation1' => $assessmentFieldValues["HdgAbbrev1"],
            'headingAbbreviation2' => $assessmentFieldValues["HdgAbbrev2"],
            'weightingFactor'      => $assessmentFieldValues["WeightingFactor"],
            'resultType'           => $resultType,
            'fileYear'             => $this->getSystemFileYear(),
            'fileSemester'         => $this->getSystemFileSemester(),
            'dueDate'              => $assessment->getDate()->getDatabase()
        ], $bindings->getParams());

        return $this->getConnector()->prepareQuery($sql, $params)->execute();
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function deleteAssessment(Activity $assessment): bool
    {
        $folderCodes = $assessment->getCategory()->getCodes()->getPropertyValues('code');
        if (!\count($folderCodes)) {
            return false;
        }

        // Common bound var placeholders
        $headingAbbreviation2 = 'headingAbbreviation2';
        $fileYear             = 'fileYear';
        $fileSemester         = 'fileSemester';

        // Delete results from the markbook
        $bindings = Predicate\Builder::getBindings($folderCodes, 'code');
        $sql      = "
            DELETE      studentBook
            FROM        StudentAssessmentMarkBook studentBook
            INNER JOIN  StaffAssessmentMarkBook staffBook ON studentBook.MarkBookSeq  = staffBook.MarkBookSeq AND
                        studentBook.FileType = staffBook.FileType AND
                        studentBook.FileYear = staffBook.FileYear AND
                        studentBook.FileSemester = staffBook.FileSemester AND
                        studentBook.ClassCampus = staffBook.ClassCampus AND
                        studentBook.ClassCode = staffBook.ClassCode
            WHERE       staffBook.HdgAbbrev2 = :{$headingAbbreviation2} AND
                        studentBook.FileYear = :{$fileYear} AND
                        studentBook.FileSemester = :{$fileSemester} AND
                        staffBook.ClassCode IN ({$bindings->getPlaceholder()})
        ";

        $params = \array_merge([
            $headingAbbreviation2 => $this->getMarkbookIdentifier($assessment),
            $fileYear             => $this->getSystemFileYear(),
            $fileSemester         => $this->getSystemFileSemester(),
        ], $bindings->getParams());

        $this->getConnector()->executeQuery($sql, $params);

        // Delete the markbook
        $sql = "
            DELETE      staffBook
            FROM        StaffAssessmentMarkBook staffBook
            WHERE       staffBook.HdgAbbrev2 = :{$headingAbbreviation2} AND
                        FileYear = :{$fileYear} AND
                        FileSemester = :{$fileSemester} AND
                        staffBook.ClassCode IN ({$bindings->getPlaceholder()})
        ";

        $statement = $this->getConnector()->prepareQuery($sql);
        return $statement->execute($params);
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
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Alaress\Database\Exception
     * @throws Exception
     */
    public function setMark($feedback): bool
    {
        $mark = null;
        $markText = null;

        if ($feedback instanceof ReturnedWork) {
            // Handle submission box result
            $box      = SubmissionBoxMapper::getInstance()->findById($feedback->getSubmissionBox()->getId());
            $activity = $this->buildActivityFromSubmissionBox($box);
            $marker   = UserMapper::getInstance()->findById($feedback->getMarker()->getId());
            $student  = UserMapper::getInstance()->findById($feedback->getOwner()->getId());

            // If the mark type is set to none then we shouldn't be saving a normalised mark
            if ($box->getMarkType()->getSlug() !== SubmissionBox\MarkType::SLUG_NONE) {
                $mark = $feedback->getNormalizedMark();
                $markText = $feedback->getHumanReadableMark();
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
            $total     = (float)$evAttempt->getTotalMarks($feedback->getQuiz()->getId(), $feedback->getId());
            $actual    = (float)$evAttempt->getMarksForAttempt($feedback->getId());
            if ($total > 0) {
                $mark = ($actual / $total) * 100;
            } else {
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

        return $this->setActivityMark($activity, $marker, $student, $mark, $markText, $comment);
    }


    /**
     * Creates a staff markbook in synergetic for this assessment
     *
     * Supports creating the markbook in a file year/semester other than the globally-configured values.
     *
     * @param Activity        $assessment
     * @param \Schoolbox\User $owner
     * @param string          $classCode
     * @param string          $resultType
     * @param int             $fileYear
     * @param int             $fileSemester
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    private function insertStaffMarkbook(
        Activity $assessment,
        \Schoolbox\User $owner,
        string $classCode,
        string $resultType,
        int $fileYear,
        int $fileSemester
    ): bool {
        $externalClass = new External_Synergetic_Class();
        $class         = $externalClass->getClass($classCode, $owner, $fileYear, $fileSemester);

        if (empty($class)) {
            return false;
        }

        $staffID = $class['MultipleStaffMarkbookFlag'] > 0 ? $owner->getExternalId() : self::GLOBAL_STAFF_ID;

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
                    WeightingFactor,
                    TestDate,
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
                    :weightingFactor,
                    :dueDate
                )
            ";

        $statement = $this->getConnector()->prepareQuery($sql, [
            'fileType'             => $class['FileType'],
            'fileYear'             => $fileYear,
            'fileSemester'         => $fileSemester,
            'staffID'              => $staffID,
            'classCampus'          => $class['ClassCampus'],
            'classCode'            => $class['ClassCode'],
            'heading'              => $assessmentFieldValues["Heading"],
            'headingAbbreviation1' => $assessmentFieldValues["HdgAbbrev1"],
            'headingAbbreviation2' => $assessmentFieldValues["HdgAbbrev2"],
            'resultType'           => $resultType,
            'weightingFactor'      => $assessmentFieldValues["WeightingFactor"],
            'dueDate'              => $assessment->getDate()->getDatabase()
        ]);

        return $statement->execute();
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
    protected function getAssessmentFieldValues(Activity $assessment): array
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
     * Determines if a student has already been marked or not, used to determining if to use insert or update
     *
     * @param $fileType
     * @param $classCampus
     * @param $classCode
     * @param $assessmentID
     * @param $studentID
     * @return bool
     */
    private function doesMarkExist($fileType, $classCampus, $classCode, $assessmentID, $studentID): bool
    {
        if (!$this->isExternalIdValid($studentID)) {
            return false;
        }

        $bindings = [
            'fileType'     => $fileType,
            'fileYear'     => $this->getUserFileYear($studentID),
            'fileSemester' => $this->getUserFileSemester($studentID),
            'classCampus'  => $classCampus,
            'classCode'    => $classCode,
            'markbookSeq'  => $assessmentID,
            'studentID'    => $studentID,
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
     * @param SubmissionBox $submissionBox
     * @return Activity
     */
    private function buildActivityFromSubmissionBox(SubmissionBox $submissionBox): Activity
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
    private function buildActivityFromQuiz(Quiz $quiz): Activity
    {
        $activity = new Activity();
        $activity->setAssessment($quiz)
            ->setOwner($quiz->getUser())
            ->setCategory($quiz->getCategory())
            ->setDate($quiz->getFinish());
        return $activity;
    }


    /**
     * Inserts/Updates/Deletes a student's mark and comment in Synergetic
     *
     * Returns whether any changes were made to the remote system.
     *
     * @param Activity        $activity
     * @param \Schoolbox\User $marker
     * @param \Schoolbox\User $student
     * @param null|float      $mark
     * @param null|string     $markText
     * @param string          $comment
     * @return bool
     * @throws \Alaress\Database\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function setActivityMark(Activity $activity, Schoolbox\User $marker, Schoolbox\User $student, $mark, $markText, $comment): bool
    {
        $studentExternalId   = $student->getExternalId();
        $studentFileYear     = $this->getUserFileYear($studentExternalId);
        $studentFileSemester = $this->getUserFileSemester($studentExternalId);

        /* We need to cater for the case where the activity resides in Schoolbox in a folder which has multiple folder codes,
         * eg ENC11, ENC12, ENC13, but the student is in a subset of codes, eg ENC11 only.
         * In this case, the student's marks should only be written to the markbooks for folder codes that the student is enrolled in.
         */
        /** @var string[] $folderCodes */
        $folderCodes = $activity->getCategory()->getCodes()->getPropertyValues('code');
        $classCodes  = \array_column($this->externalUser->getSubjectList($student), 'code');

        // Only push to codes are currently attached to the assessment's folder, and the student is enrolled in
        $markbookFolderCodes = \array_intersect($folderCodes, $classCodes);

        // In this context, we need to ensure the fileYear etc match the student, not the marker
        $this->insertMarkbooksIfNotExists($activity, $markbookFolderCodes, $studentFileYear, $studentFileSemester);

        $assessmentRemoteList = $this->getSynergeticMarkbookList($activity, $studentFileYear, $studentFileSemester);

        /* Ensure we cater for 0 which coerces to 'false'.
         * If no mark, set the insert value to a magic number.
         */
        $markValue = null === $mark
            ? self::NO_MARK_GIVEN
            : round($mark,2);

        $hasMarkOrComment = (null !== $mark) || ('' !== trim($comment)) || ('' !== trim($markText)) ;

        $changesMade = false;
        foreach ($assessmentRemoteList as $assessmentRemote) {
            // Ignore markbooks if the student is not involved in them
            if (!\in_array($assessmentRemote['ClassCode'], $markbookFolderCodes, true)) {
                continue;
            }

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
                if ($this->insertActivityMark($activity, $assessmentRemote, $marker, $student, $markValue, $comment)) {
                    $changesMade = true;
                }
            } elseif ($hasMarkOrComment) {
                // Mark/Comment in synergetic + SB
                if ($this->updateActivityMark($assessmentRemote, $student, $markValue, $comment)) {
                    $changesMade = true;
                }
            } elseif ($remoteMarkExists) {
                // Mark/Comment in synergetic , none in SB
                if ($this->deleteActivityMark($assessmentRemote, $student)) {
                    $changesMade = true;
                }
            }
        }
        return $changesMade;
    }


    /**
     * Update a student's existing mark/comment in synergetic
     *
     * @param array           $assessmentRemote
     * @param \Schoolbox\User $student
     * @param                 $markValue
     * @param                 $comment
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    private function updateActivityMark(
        array $assessmentRemote,
        Schoolbox\User $student,
        $markValue,
        $comment
    ): bool {
        $sql       = '
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
        $params    = [
            'mark'         => $markValue,
            'topicComment' => $comment,
            'fileType'     => $assessmentRemote['FileType'],
            'fileYear'     => $this->getUserFileYear($student->getExternalId()),
            'fileSemester' => $this->getUserFileSemester($student->getExternalId()),
            'classCampus'  => $assessmentRemote['ClassCampus'],
            'classCode'    => $assessmentRemote['ClassCode'],
            'ID'           => $student->getExternalId(),
            'markbookSeq'  => $assessmentRemote['MarkBookSeq'],
        ];
        $statement = $this->getConnector()->prepareQuery($sql);
        return $statement->execute($params);
    }


    /**
     * Delete a student's mark/comment from synergetic
     *
     * @param array           $assessmentRemote
     * @param \Schoolbox\User $student
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    private function deleteActivityMark(array $assessmentRemote, Schoolbox\User $student): bool
    {
        $sql       = '
            DELETE FROM StudentAssessmentMarkBook
            WHERE       FileType = :fileType AND
                        FileYear = :fileYear AND
                        FileSemester = :fileSemester AND
                        ClassCampus = :classCampus AND
                        ClassCode = :classCode AND
                        ID = :ID AND
                        MarkBookSeq = :markbookSeq
        ';
        $params    = [
            'fileType'     => $assessmentRemote['FileType'],
            'fileYear'     => $this->getUserFileYear($student->getExternalId()),
            'fileSemester' => $this->getUserFileSemester($student->getExternalId()),
            'classCampus'  => $assessmentRemote['ClassCampus'],
            'classCode'    => $assessmentRemote['ClassCode'],
            'ID'           => $student->getExternalId(),
            'markbookSeq'  => $assessmentRemote['MarkBookSeq'],
        ];
        $statement = $this->getConnector()->prepareQuery($sql);
        return $statement->execute($params);
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
     * @throws \Alaress\Database\Exception
     */
    private function insertActivityMark(
        Activity $activity,
        array $assessmentRemote,
        Schoolbox\User $marker,
        Schoolbox\User $student,
        $markValue,
        $comment
    ): bool {
        $sql       = "
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
        $params    = [
            'fileType'         => $assessmentRemote['FileType'],
            'fileYear'         => $this->getUserFileYear($student->getExternalId()),
            'fileSemester'     => $this->getUserFileSemester($student->getExternalId()),
            'classCampus'      => $assessmentRemote['ClassCampus'],
            'classCode'        => $assessmentRemote['ClassCode'],
            'externalID'       => $student->getExternalId(),
            'markbookSeq'      => $assessmentRemote['MarkBookSeq'],
            'mark'             => $markValue,
            'topicComment'     => $comment,
            'resultDate'       => $activity->getDate()->getDatabase(),
            'markerExternalId' => $marker->getExternalId(),
        ];
        $statement = $this->getConnector()->prepareQuery($sql);
        return $statement->execute($params);
    }


    /**
     * Determine what result type this assessment should have.
     *
     * @param Activity $assessment
     * @return string
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function getResultType(Activity $assessment): string
    {
        $defaultType = 'OGRaw';

        // Does this assessment have a work type with an external result type?
        if (null !== $assessment->getType()) {
            $workType   = WorkTypeMapper::getInstance()->findById($assessment->getType());
            $resultType = $workType->getExternalResultType();
        }

        // We know OGRaw is in the DB, and if it's not there's bigger problems
        if (empty($resultType)) {
            return $defaultType;
        }

        // Validate the result type is actually in the DB
        $sql   = "
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
    private function truncateForDatabase($string, $length): string
    {
        return mb_substr($string, 0, $length, self::CHARACTER_ENCODING);
    }


    /**
     * Returns the string used to identify this assessment in the markbook.
     *
     * @param Activity $assessment
     * @return string
     */
    private function getMarkbookIdentifier(Activity $assessment): string
    {
        // This column is limited to 10 characters
        $id = ($assessment->isAssessment() ? self::ASSESSMENT_PREFIX : self::SUBMISSION_PREFIX) . $assessment->getId();
        return $this->truncateForDatabase($id, 10);
    }


    /**
     * Ensures this assessment has a markbook in each relevant folder, fileYear, and fileSemester
     *
     * Returns true if any markbooks were created.
     *
     * @param Activity $activity
     * @param string[] $folderCodes
     * @param int      $fileYear
     * @param int      $fileSemester
     * @return bool
     * @throws \Alaress\Database\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function insertMarkbooksIfNotExists(Activity $activity, array $folderCodes, int $fileYear, int $fileSemester): bool
    {
        if (!\count($folderCodes)) {
            return false;
        }

        $remoteAssessmentList = $this->getSynergeticMarkbookList($activity, $fileYear, $fileSemester);
        $remoteFolderCodes    = \array_column($remoteAssessmentList, 'ClassCode');
        $codesNotInRemote     = \array_diff($folderCodes, $remoteFolderCodes);
        $resultType           = $this->getResultType($activity);

        $isCreated = false;
        foreach ($codesNotInRemote as $folderCode) {
            if ($this->insertStaffMarkbook($activity, $activity->getOwner(), $folderCode, $resultType, $fileYear, $fileSemester)) {
                $isCreated = true;
            }
        }

        return $isCreated;
    }


    /**
     * Returns the globally-configured Synergetic FileYear
     *
     * @return int
     */
    private function getSystemFileYear(): int
    {
        return $this->fileYearOverride ?? $this->externalUser->getCurrentYear();
    }


    /**
     * Returns the globally-configured Synergetic FileSemester
     *
     * @return int
     */
    private function getSystemFileSemester(): int
    {
        return $this->fileSemesterOverride ?? $this->externalUser->getCurrentSemester();
    }


    /**
     * Returns the FileYear configured for the specified user
     *
     * @param int $externalId
     * @return int
     */
    private function getUserFileYear(int $externalId): int
    {
        return $this->fileYearOverride ?? $this->externalUser->getCurrentYear($externalId);
    }


    /**
     * Returns the FileSemester configured for the specified user
     *
     * @param int $externalId
     * @return int
     */
    private function getUserFileSemester(int $externalId): int
    {
        return $this->fileSemesterOverride ?? $this->externalUser->getCurrentSemester($externalId);
    }


    /**
     * Returns the set of markbooks in synergetic that match this assessment, and the specified file year/semester
     *
     * @param Activity $activity
     * @param int      $fileYear
     * @param int      $fileSemester
     * @return array
     */
    private function getSynergeticMarkbookList(Activity $activity, int $fileYear, int $fileSemester): array
    {
        $folderCodes = $activity->getCategory()->getCodes()->getPropertyValues('code');
        if (!\count($folderCodes)) {
            return [];
        }

        $folderCodeBindings = Predicate\Builder::getBindings($folderCodes, 'code');

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
                      FileSemester = :fileSemester AND
                      ClassCode IN ({$folderCodeBindings->getPlaceholder()})
        ";

        $params = \array_merge([
            'markbookIdentifier' => $this->getMarkbookIdentifier($activity),
            'fileYear'           => $fileYear,
            'fileSemester'       => $fileSemester,
        ], $folderCodeBindings->getParams());

        return $this->getConnector()->executeQuery($sql, $params)->fetchAll();
    }
}
