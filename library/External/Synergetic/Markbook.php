<?php

/**
 * @stolenfrom  James Leckie <jleckie@alaress.com.au>
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;

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

    /**
     * These values are set in the config table via a variable 'synergetic_mark_sync_value'. This will configure what
     * type of mark we will synchronise back to the SIS.  Either human readable or normalised.  The default is normalised.
     */
    private const NORMALISED = 0;
    private const HUMAN_READABLE = 1;

    /** @var External_UserInterface */
    private $externalUser;

    private External_Synergetic_Class $externalClass;

    /** @var int|null */
    private $fileYearOverride;

    /** @var int|null */
    private $fileSemesterOverride;

    /**
     * A cache of markbook result types.
     *
     * @var array key:   the assessment's work type ID
     *            value: the corresponding result type in Synergetic
     */
    private $resultTypeCache = [];

    /**
     * A cache of assessments in the synergetic markbook
     * Is keyed with FileYear, FileSemester, FolderCodes, AssessmentCode
     */
    private $synMarkbook = [];

    /**
     * The type of mark to sync, normal or human readable
     * @var int
     */
    private $markType;

    /**
     * @var array<int, \Schoolbox\Role>
     */
    private array $roles = [];

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * If $fileYearOverride and $fileSemesterOverride are provided, they will override both the
     * globally-configured synergetic file year/semester and also each student's file year/semester.
     *
     * @param int      $markType  This configures to sync normalised (1) or human readable (2)
     * @param int|null $fileYearOverride
     * @param int|null $fileSemesterOverride
     * @todo There's a heap of dependencies in this class that are plucked from the ether. They should be constructor dependencies, injected by a factory.
     */
    public function __construct(int $markType, LoggerInterface $logger, int $fileYearOverride = null, int $fileSemesterOverride = null)
    {
        if (null === $fileYearOverride xor null === $fileSemesterOverride) {
            throw new InvalidArgumentException('You must override both year and semester, or neither');
        }
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable
        $this->setupDatabase();
        $this->externalUser         = new External_User();
        $this->externalClass        = new External_Synergetic_Class();
        $this->markType             = $markType;
        $this->fileYearOverride     = $fileYearOverride;
        $this->fileSemesterOverride = $fileSemesterOverride;
        $this->logger               = $logger;
    }

    /**
     * The Synergetic markbook syncs due works and quizzes, so long as they are
     * not spec assessments.
     *
     * @inheritDoc
     */
    public function isSyncable(IAssessable $assessment): bool
    {
        if ($assessment->isSpecification()) {
            return false;
        }
        return $assessment instanceof Quiz || $assessment instanceof DueWork;
    }


    /**
     * Syncs assessment from Schoolbox to Synergetic.
     *
     * If the activity belongs to a Schoolbox folder with multiple folder codes,
     * this will create a markbook in Synergetic per folder code.
     *
     * Creates any assessments in Synergetic's globally-configured year and
     * semester, rather than in the context of a specific user.
     *
     * @inheritdoc
     *
     * @throws \Alaress\Database\Exception
     */
    public function syncAssessment(IAssessable $assessment): bool
    {
        if (!$this->isSyncable($assessment)) {
            return false;
        }

        $folderCodes = $this->retrieveFolderCodesFromAssessment($assessment);
        if (!count($folderCodes)) {
            $this->logger->warning("Unable to sync assessment {$assessment->getId()}: No folder code found");
            return false;
        }

        if (
            $this->insertMarkbooksIfNotExists(
                $assessment,
                $folderCodes,
                $this->getSystemFileYear(),
                $this->getSystemFileSemester()
            )
        ) {
            return true;
        } else {
            $markbookId = $this->getMarkbookIdentifier($assessment);
            if (!isset($markbookId)) {
                $this->logger->warning("Unable to sync assessment {$assessment->getId()}: Could not get markbook identifier");
                return false;
            }

            $assessmentFieldValues = $this->getAssessmentFieldValues($assessment);
            $resultType            = $this->getResultType($assessment);

            $sql = "
                UPDATE StaffAssessmentMarkBook
                SET    Heading         = :heading,
                       HdgAbbrev1      = :headingAbbreviation1,
                       WeightingFactor = :weightingFactor,
                       ResultType      = :resultType,
                       TestDate        = :dueDate
                WHERE  HdgAbbrev2      = :headingAbbreviation2
                AND    FileYear        = :fileYear
                AND    FileSemester    = :fileSemester
            ";

            $params = [
                'heading'              => $assessmentFieldValues["Heading"],
                'headingAbbreviation1' => $assessmentFieldValues["HdgAbbrev1"],
                'headingAbbreviation2' => $markbookId,
                'weightingFactor'      => $assessmentFieldValues["WeightingFactor"],
                'resultType'           => $resultType,
                'fileYear'             => $this->getSystemFileYear(),
                'fileSemester'         => $this->getSystemFileSemester(),
                'dueDate'              => $assessment->getDueAt()->getDatabase(),
            ];

            try {
                $this->getConnector()->prepareQuery($sql, $params)->execute();
                return true;
            } catch (\Exception $e) {
                $this->logger->warning("Unable to sync assessment {$assessment->getId()}: Unable to update staff markbook {$markbookId}: {$e->getMessage()}");
                return false;
            }
        }
    }


    /**
     * Returns TRUE if there are any markbooks in synergetic for this assessment.
     *
     * Only checks Synergetic's current globally-configured year and semester,
     * rather than in the context of a specific user.
     *
     * Moving an assessment to a new folder, or changing the assessment's
     * folder's codes, will break the link to synergetic's markbooks.
     *
     * IMPORTANT: A TRUE return value does not mean that Schoolbox and
     * Synergetic are in sync regarding folders.
     *
     * Consider case where a folder has a single folder code, and an assessment
     * is created. This will create a markbook in synergetic.
     * If the user adds another class code to the folder, this method will still
     * return true, but we still need to CREATE the second markbook in
     * synergetic before we attempt to apply an update().
     * This extra logic is implemented in insertMarkbooksIfNotExists().
     *
     * @inheritdoc
     */
    public function hasAssessment(IAssessable $assessment): bool
    {
        if (!$this->isSyncable($assessment)) {
            return false;
        }

        $folderCodes = $this->retrieveFolderCodesFromAssessment($assessment);
        if (!count($folderCodes)) {
            $this->logger->warning("No folder code found for assessment {$assessment->getId()}");
            return false;
        }

        $markbookId = $this->getMarkbookIdentifier($assessment);
        if (!isset($markbookId)) {
            return false;
        }

        $sql = "
            SELECT    COUNT(*) AS result
            FROM      StaffAssessmentMarkBook
            WHERE     HdgAbbrev2 = :markbookIdentifier AND
                      FileYear = :fileYear AND
                      FileSemester = :fileSemester AND
                      {$this->externalClass->getClassCodeQuery(implode(',', $folderCodes))}
        ";

        $params = [
            'markbookIdentifier' => $markbookId,
            'fileYear'           => $this->getSystemFileYear(),
            'fileSemester'       => $this->getSystemFileSemester(),
        ];

        $statement = $this->getConnector()->executeQuery($sql, $params);

        return $statement->fetchColumn() > 0;
    }


    /**
     * @param IAssessable $assessment
     * @return bool
     */
    public function deleteAssessment(IAssessable $assessment): bool
    {
        if (!$this->isSyncable($assessment)) {
            return false;
        }

        $folderCodes = $this->retrieveFolderCodesFromAssessment($assessment);
        if (!count($folderCodes)) {
            $this->logger->warning("No folder code found for assessment {$assessment->getId()}");
            return false;
        }

        $markbookId = $this->getMarkbookIdentifier($assessment, true);
        if (!isset($markbookId)) {
            return false;
        }

        // Common bound var placeholders
        $headingAbbreviation2 = 'headingAbbreviation2';
        $fileYear             = 'fileYear';
        $fileSemester         = 'fileSemester';

        // Delete results from the markbook
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
                        {$this->externalClass->getClassCodeQuery(implode(',', $folderCodes), 'staffBook')}
        ";

        $params = [
            $headingAbbreviation2 => $markbookId,
            $fileYear             => $this->getSystemFileYear(),
            $fileSemester         => $this->getSystemFileSemester(),
        ];

        $this->getConnector()->executeQuery($sql, $params);

        // Delete the markbook
        $sql = "
            DELETE      staffBook
            FROM        StaffAssessmentMarkBook staffBook
            WHERE       staffBook.HdgAbbrev2 = :{$headingAbbreviation2} AND
                        FileYear = :{$fileYear} AND
                        FileSemester = :{$fileSemester} AND
                        {$this->externalClass->getClassCodeQuery(implode(',', $folderCodes), 'staffBook')}
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
     * @param IFeedback $feedback
     * @return bool
     * @throws \Alaress\Database\Exception
     * @throws InvalidArgumentException
     * @throws UnderflowException
     */
    public function syncFeedback(IFeedback $feedback): bool
    {
        if (!$this->isSyncable($feedback->getAssessable())) {
            throw new InvalidArgumentException('Unable to sync this assessment type because it is a specification or unsupported');
        }

        if ($feedback->isDraft()) {
            $this->logger->warning("Unable to sync feedback {$feedback->getId()}: Feedback is draft");
            return false;
        }

        $student = $feedback->getReviewee();
        $marker  = $feedback->getReviewer();

        // Don't sync marks if:
        // * Student's ID is invalid
        // * Marker's ID is invalid
        if (!$this->isExternalIdValid($student->getExternalId())) {
            $this->logger->warning("Unable to sync feedback {$feedback->getId()} : Student ({$student->getUsername()}) has an invalid external ID: {$student->getExternalId()}");
            throw new InvalidArgumentException('Student (' . $student->getUsername() . ') has an invalid external ID: ' . $student->getExternalId());
        }
        if (!$this->isExternalIdValid($marker->getExternalId())) {
            $this->logger->warning("Unable to sync feedback {$feedback->getId()} : Provider of feedback ({$marker->getUsername()}) has invalid external ID: {$marker->getExternalId()}");
            throw new InvalidArgumentException('Provider of feedback (' . $marker->getUsername() . ') has invalid external ID: ' . $marker->getExternalId());
        }

        return $this->setActivityMark($feedback);
    }


    /**
     * @inheritDoc
     */
    public function deleteFeedback(IAssessable $assessment, User $student): bool
    {
        if (!$this->isSyncable($assessment)) {
            return false;
        }

        $studentExternalId = $student->getExternalId();
        if (!$this->isExternalIdValid($studentExternalId)) {
            return false;
        }

        $studentFileYear      = $this->getUserFileYear($studentExternalId);
        $studentFileSemester  = $this->getUserFileSemester($studentExternalId);
        $assessmentRemoteList = $this->getSynergeticMarkbookList($assessment, $studentFileYear, $studentFileSemester);
        foreach ($assessmentRemoteList as $assessmentRemote) {
            $this->deleteActivityMark($assessmentRemote, $student);
        }

        return true;
    }


    /**
     * Creates a staff markbook in synergetic for this assessment
     *
     * Supports creating the markbook in a file year/semester other than the globally-configured values.
     *
     * @param IAssessable $assessment
     * @param User        $owner
     * @param string      $classCode
     * @param string      $resultType
     * @param int         $fileYear
     * @param int         $fileSemester
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    private function insertStaffMarkbook(
        IAssessable $assessment,
        User $owner,
        string $classCode,
        string $resultType,
        int $fileYear,
        int $fileSemester
    ): bool {
        $markbookId = $this->getMarkbookIdentifier($assessment);
        if (!isset($markbookId)) {
            $this->logger->warning("Unable to create staff markbook for assessment {$assessment->getId()}: Could not get markbook identifier");
            return false;
        }

        $class = $this->externalClass->getClass($classCode, $owner, $fileYear, $fileSemester);

        if (empty($class)) {
            $this->logger->warning("Unable to create staff markbook for assessment {$assessment->getId()} for class code {$classCode}");
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
                    TestDate
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
            'headingAbbreviation2' => $markbookId,
            'resultType'           => $resultType,
            'weightingFactor'      => $assessmentFieldValues["WeightingFactor"],
            'dueDate'              => $assessment->getDueAt()->getDatabase(),
        ]);

        try {
            $statement->execute();
            return true;
        } catch (\Exception $e) {
            $this->logger->warning("Unable to insert StaffAssessmentMarkBook for folder {$class['ClassCode']} assessment {$assessment->getId()}: {$e->getMessage()}");
            return false;
        }
    }


    /**
     * Gets database field values out of an assessment. Gets the following fields:
     * * Heading
     * * HdgAbbrev1
     * * WeightingFactor
     *
     * @param IAssessable $assessment
     *
     * @return array
     */
    private function getAssessmentFieldValues(IAssessable $assessment): array
    {
        $weight = $assessment->getWeight();

        // DB-level limits:
        // Heading: 255 chars
        // HdgAbbrev1: 10 chars
        // WeightingFactor: 0-9999
        return [
            'Heading'         => $this->truncateForDatabase($assessment->getTitle(), 255),
            'HdgAbbrev1'      => $this->truncateForDatabase($assessment->getTitle(), 10),
            'WeightingFactor' => max(0, min($weight, 9999)),
        ];
    }


    /**
     * Determines if a student has already been marked or not, used to determining if to use insert or update
     *
     * @param array     $assessmentRemote
     * @param IFeedback $feedback
     * @return bool
     */
    private function doesMarkExist(array $assessmentRemote, IFeedback $feedback): bool
    {
        $student = $feedback->getReviewee();
        if (!$this->isExternalIdValid($student->getExternalId())) {
            return false;
        }

        $params = $this->determineActivityMarkKeyParams($assessmentRemote, $student);

        $sql = "
            SELECT  COUNT(*) AS result
            FROM    StudentAssessmentMarkBook
            WHERE   FileType     = :fileType
            AND     FileYear     = :fileYear
            AND     FileSemester = :fileSemester
            AND     ClassCampus  = :classCampus
            AND     ClassCode    = :classCode
            AND     MarkBookSeq  = :markbookSeq
            AND     ID           = :externalID
        ";

        return (bool)$this->getConnector()->executeQuery($sql, $params)->fetchColumn();
    }


    /**
     * Inserts/Updates/Deletes a student's mark and comment in Synergetic
     *
     * Returns whether any changes were made to the remote system.
     *
     * @param IFeedback $feedback
     * @return bool
     */
    private function setActivityMark(IFeedback $feedback): bool
    {
        $activity            = $feedback->getAssessable();
        $student             = $feedback->getReviewee();
        $mark                = $feedback->getNormalisedMark();
        $markText            = $feedback->getHumanReadableMark();
        $comment             = $feedback->getComment();
        $studentExternalId   = $student->getExternalId();
        $studentFileYear     = $this->getUserFileYear($studentExternalId);
        $studentFileSemester = $this->getUserFileSemester($studentExternalId);

        /* We need to cater for the case where the activity resides in Schoolbox in a folder which has multiple folder codes,
         * eg ENC11, ENC12, ENC13, but the student is in a subset of codes, eg ENC11 only.
         * In this case, the student's marks should only be written to the markbooks for folder codes that the student is enrolled in.
         */
        /** @var string[] $folderCodes */
        $folderCodes = $this->retrieveFolderCodesFromAssessment($activity);
        if (count($folderCodes) === 0) {
            $this->logger->warning("Unable to sync this feedback {$feedback->getId()} : No folder code found for assessment {$activity->getId()}");
            return false;
        }

        // Performance optimization:
        // Only filter the folder codes to the student's class codes if the
        // folder has more than one folder code:
        // otherwise assume the folder's code is one of the student's
        if (count($folderCodes) > 1) {
            // make sure that role is hydrated
            if (!$student->getRole()->getType()) {
                $student->setRole($this->getRole($student->getRole()->getId()));
            }
            $classCodes = array_column($this->externalUser->getSubjectList($student), 'code');

            // Only push to codes are currently attached to the assessment's folder, and the student is enrolled in
            $markbookFolderCodes = array_intersect(
                $this->normalizeClassCodes($folderCodes),
                $this->normalizeClassCodes($classCodes)
            );
        } else {
            // one folder code
            $markbookFolderCodes = $this->normalizeClassCodes($folderCodes);
        }

        // In this context, we need to ensure the fileYear etc match the student, not the marker
        $this->insertMarkbooksIfNotExists($activity, $markbookFolderCodes, $studentFileYear, $studentFileSemester);

        $assessmentRemoteList = $this->getSynergeticMarkbookList($activity, $studentFileYear, $studentFileSemester);

        if (!count($assessmentRemoteList)) {
            $this->logger->warning("Unable to sync this feedback {$feedback->getId()} : Unable to find StaffAssessmentMarkBook for HdgAbbrev2 {$this->getMarkbookIdentifier($activity)}");
            throw new UnderflowException('Unable to find assessment:  ' .
                                         $this->getMarkbookIdentifier($activity) . ' in ' .
                                         implode(',', $markbookFolderCodes));
        }

        $hasMarkOrComment = (null !== $mark) || ('' !== trim((string)$comment)) || ('' !== trim((string)$markText));

        $changesMade = false;
        foreach ($assessmentRemoteList as $assessmentRemote) {
            // Ignore markbooks if the student is not involved in them
            if (
                !in_array(
                    $this->normalizeClassCode($assessmentRemote['ClassCode']),
                    $markbookFolderCodes,
                    true
                ) &&
                !in_array(
                    $this->normalizeClassCode("{$assessmentRemote['ClassCampus']}/{$assessmentRemote['ClassCode']}"),
                    $markbookFolderCodes,
                    true
                )
            ) {
                continue;
            }

            // Does the student already have a mark for the assessment in Synergetic?
            $remoteMarkExists = $this->doesMarkExist($assessmentRemote, $feedback);

            if (!$remoteMarkExists && $hasMarkOrComment) {
                // No mark in synergetic, mark/comment in SB
                if ($this->insertActivityMark($assessmentRemote, $feedback)) {
                    $changesMade = true;
                }
            } elseif ($hasMarkOrComment) {
                // Mark/Comment in synergetic + SB
                if ($this->updateActivityMark($assessmentRemote, $feedback)) {
                    $changesMade = true;
                }
            } elseif ($remoteMarkExists) {
                // Mark/Comment in synergetic , none in SB
                if ($this->deleteActivityMark($assessmentRemote, $feedback->getReviewee())) {
                    $changesMade = true;
                }
            }
        }
        return $changesMade;
    }


    /**
     * Update a student's existing mark/comment in synergetic
     *
     * @param array     $assessmentRemote
     * @param IFeedback $feedback
     * @return bool
     */
    private function updateActivityMark(array $assessmentRemote, IFeedback $feedback): bool
    {
        // This now updates the result date, modified by ID and modified date
        // fields.
        // (I think it should update the modified date?)
        $sql       = '
            UPDATE  StudentAssessmentMarkBook
            SET     Result       = :mark,
                    TopicComment = :topicComment,
                    ResultDate   = :resultDate,
                    ModifiedByID = :markerExternalId,
                    ModifiedDate = CURRENT_TIMESTAMP
            WHERE   FileType     = :fileType
            AND     FileYear     = :fileYear
            AND     FileSemester = :fileSemester
            AND     ClassCampus  = :classCampus
            AND     ClassCode    = :classCode
            AND     MarkBookSeq  = :markbookSeq
            AND     ID           = :externalID
        ';
        $keyParams   = $this->determineActivityMarkKeyParams($assessmentRemote, $feedback->getReviewee());
        $valueParams = $this->determineActivityMarkValueParams($feedback);
        $params      = array_merge($keyParams, $valueParams);
        $statement   = $this->getConnector()->prepareQuery($sql);
        try {
            $statement->execute($params);
            return true;
        } catch (\Exception $e) {
            $this->logger->warning("Unable to update feedback {$feedback->getId()} in StudentAssessmentMarkbook {$params['markbookSeq']} : {$e->getMessage()}");
            return false;
        }
    }


    /**
     * Delete a student's mark/comment from synergetic
     *
     * @param array $assessmentRemote
     * @param User  $student
     * @return bool
     */
    private function deleteActivityMark(array $assessmentRemote, User $student): bool
    {
        $sql       = '
            DELETE FROM StudentAssessmentMarkBook
            WHERE   FileType     = :fileType
            AND     FileYear     = :fileYear
            AND     FileSemester = :fileSemester
            AND     ClassCampus  = :classCampus
            AND     ClassCode    = :classCode
            AND     MarkBookSeq  = :markbookSeq
            AND     ID           = :externalID
        ';
        $params = $this->determineActivityMarkKeyParams($assessmentRemote, $student);
        $statement = $this->getConnector()->prepareQuery($sql);
        try {
            $statement->execute($params);
            return true;
        } catch (\Exception $e) {
            $this->logger->warning("Unable to delete StudentAssessmentMarkbook {$params['markbookSeq']} : {$e->getMessage()}");
            return false;
        }
    }


    /**
     * Insert a student's mark/comment into Synergetic
     *
     * @param array     $assessmentRemote
     * @param IFeedback $feedback
     * @return bool
     */
    private function insertActivityMark(array $assessmentRemote, IFeedback $feedback): bool
    {
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
        $keyParams   = $this->determineActivityMarkKeyParams($assessmentRemote, $feedback->getReviewee());
        $valueParams = $this->determineActivityMarkValueParams($feedback);
        $statement   = $this->getConnector()->prepareQuery($sql);

        try {
            $result = $statement->execute(array_merge($keyParams, $valueParams));
        } catch (\Exception $e) {
            $this->logger->warning("Failed to insert activity mark for feedback {$feedback->getId()}: {$e->getMessage()}");
            return false;
        }
        return $result;
    }


    /**
     * Determine what result type this assessment should have.
     *
     * @param IAssessable $assessment
     * @return string
     */
    private function getResultType(IAssessable $assessment): string
    {
        $defaultType = 'OGRaw';

        // Does this assessment have a work type with an external result type?
        $resultType = null;
        $workType   = $assessment->getWorkType();
        $workTypeId = null;
        if ($workType) {
            $workTypeId = $workType->getId();

            // Use the cache if we can
            if (array_key_exists($workTypeId, $this->resultTypeCache)) {
                return $this->resultTypeCache[$workTypeId];
            }

            $resultType = $workType->getExternalResultType();
            if ($workTypeId && empty($resultType)) {
                // Reload the work type from the DB, because it's not guaranteed
                // to be fully hydrated
                try {
                    $workType   = WorkTypeMapper::getInstance()->findById($workTypeId);
                    $resultType = $workType->getExternalResultType();
                } catch (MapperException $e) {
                    // noop: probably fall back to the default type
                }
            }
        }

        // We know OGRaw is in the DB, and if it's not there's bigger problems
        if (empty($resultType)) {
            return $defaultType;
        }

        // Validate the result type is in the DB: if not use the default
        $sql   = "
            SELECT COUNT(*)
            FROM   luReportResultType
            WHERE  Code = :resultType
        ";
        $count = $this->getConnector()->executeQuery($sql, ['resultType' => $resultType,])->fetchColumn();
        if ((int)$count === 0) {
            $resultType = $defaultType;
        }
        $this->resultTypeCache[$workTypeId] = $resultType;
        return $resultType;
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
     * @param IAssessable $assessment
     * @param bool        $includeDeleted
     * @return string|null
     */
    private function getMarkbookIdentifier(IAssessable $assessment, bool $includeDeleted = false): ?string
    {
        // This column is limited to 10 characters
        // Need the original ID as well here?
        $prefix  = '';
        $syncIds = [];
        $param = $includeDeleted ? ['deleted' => true] : [];
        if ($assessment instanceof DueWork) {
            $prefix  = self::SUBMISSION_PREFIX;
            $syncIds = SubmissionBoxMapper::getInstance()->findIdsByColumn('assessment_id', $assessment->getId(), $param);
        } elseif ($assessment instanceof Quiz) {
            $prefix  = self::ASSESSMENT_PREFIX;
            $syncIds = QuizMapper::getInstance()->findIdsByColumn('assessment_id', $assessment->getId(), $param);
        }

        // Must be exactly one item
        if (count($syncIds) !== 1) {
            return null;
        }

        return $this->truncateForDatabase($prefix . $syncIds[0], 10);
    }


    /**
     * Ensures this assessment has a markbook in each relevant folder, fileYear
     * and fileSemester, creating the markbook entry if it does not exist.
     *
     * Returns true if ANY markbooks are created in Synergetic. Consider the
     * case where a SB assessment resides on a folder with 2 folder codes, but
     * only 1 markbook in synergetic: this method will return TRUE, even though
     * it is only a partial truth.
     *
     * @param IAssessable $activity
     * @param string[]    $folderCodes
     * @param int         $fileYear
     * @param int         $fileSemester
     * @return bool
     */
    private function insertMarkbooksIfNotExists(IAssessable $activity, array $folderCodes, int $fileYear, int $fileSemester): bool
    {
        if (!count($folderCodes)) {
            return false;
        }

        try {
            $owner = $this->retrieveOwnerFromAssessment($activity);
        } catch (MapperException $e) {
            $this->logger->warning("Unable to insert markbook for assessment {$activity->getId()}: Failed to find the assessment");
            return false;
        }
        if (!$owner) {
            $this->logger->warning("Unable to insert markbook for assessment {$activity->getId()}: Failed to find its owner");
              return false;
        }
        if (!$this->isExternalIdValid($owner->getExternalId())) {
            $this->logger->warning("Unable to insert markbook for assessment {$activity->getId()}: Owner ({$owner->getUsername()}) has an invalid external ID: {$owner->getExternalId()}");
            return false;
        }

        $remoteAssessmentList = $this->getSynergeticMarkbookList($activity, $fileYear, $fileSemester);

        $remoteFolderCodes = array_column($remoteAssessmentList, 'ClassCode');
        $remoteCampusCodes = array_column($remoteAssessmentList, 'ClassCampus');

        $codesNotInRemote     = array_diff(
            $this->normalizeClassCodes($folderCodes),
            $this->normalizeClassCodes($remoteFolderCodes, $remoteCampusCodes)
        );
        $resultType           = $this->getResultType($activity);
        $isCreated            = false;
        foreach ($codesNotInRemote as $folderCode) {
            if (
                $this->insertStaffMarkbook(
                    $activity,
                    $owner,
                    $folderCode,
                    $resultType,
                    $fileYear,
                    $fileSemester
                )
            ) {
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
        return $this->fileYearOverride ?? (int)$this->externalUser->getCurrentYear();
    }


    /**
     * Returns the globally-configured Synergetic FileSemester
     *
     * @return int
     */
    private function getSystemFileSemester(): int
    {
        return $this->fileSemesterOverride ?? (int)$this->externalUser->getCurrentSemester();
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
     * @param IAssessable $activity
     * @param int         $fileYear
     * @param int         $fileSemester
     * @return array
     */
    private function getSynergeticMarkbookList(IAssessable $activity, int $fileYear, int $fileSemester): array
    {
        $folderCodes = $this->retrieveFolderCodesFromAssessment($activity);
        if (!count($folderCodes)) {
            return [];
        }

        $markbookId = $this->getMarkbookIdentifier($activity);
        if (!isset($markbookId)) {
            return [];
        }

        $folderCodeKey = implode(';', $folderCodes);
        if (isset($this->synMarkbook[$fileYear][$fileSemester][$folderCodeKey][$markbookId])) {
            return $this->synMarkbook[$fileYear][$fileSemester][$folderCodeKey][$markbookId];
        }

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
                      {$this->externalClass->getClassCodeQuery(implode(',', $folderCodes))}
        ";

        $params = [
            'markbookIdentifier' => $markbookId,
            'fileYear'           => $fileYear,
            'fileSemester'       => $fileSemester,
        ];

        $result = $this->getConnector()->executeQuery($sql, $params)->fetchAll();
        if (count($result)) {
            $this->synMarkbook[$fileYear][$fileSemester][$folderCodeKey][$markbookId] = $result;
        }
        return $result;
    }


    /**
     * Retrieve the owner from the assessment.
     *
     * IAssessable objects do not have the concept of an owner: only the
     * underlying due work or quiz store this.
     *
     * @param IAssessable $assessment
     * @return User|null
     * @throws MapperException
     */
    private function retrieveOwnerFromAssessment(IAssessable $assessment): ?User
    {
        if ($assessment instanceof DueWork) {
            return SubmissionBoxMapper::getInstance()
                                      ->findOneByColumn('assessment_id', $assessment->getId())
                                      ->getOwner();
        }
        if ($assessment instanceof Quiz) {
            return QuizMapper::getInstance()
                             ->findOneByColumn('assessment_id', $assessment->getId())
                             ->getUser();
        }
        return null;
    }


    /**
     * Retrieve the folder from the assessment
     *
     * @param IAssessable $assessment
     * @return FolderModel
     */
    private function retrieveFolderFromAssessment(IAssessable $assessment): FolderModel
    {
        return $assessment->getBaseContext()->getFolder();
    }


    /**
     * Retrieve folder codes out of the assessment
     *
     * @param IAssessable $assessment
     * @return array
     */
    private function retrieveFolderCodesFromAssessment(IAssessable $assessment): array
    {
        $folderMapper = FolderMapper::getInstance();
        $folder      = $this->retrieveFolderFromAssessment($assessment);
        $folderCodes = $folder->getCodes();
        if (!isset($folderCodes)) {
            // Need to actually load folder codes
            try {
                $folderCodes = $folderMapper->findById($folder->getId())->getCodes();
            } catch (MapperException $e) {
                $this->logger->warning("Failed to find folder {$folder->getId()}:" . $e->getMessage());
                return [];
            }
        }
        // If no folder code, attempt to get it from archived folder
        if ($folderCodes->count() === 0) {
            $folderCodeMapper = \Schoolbox\Resource\Folder\Code\Mapper::getInstance();
            $folderCodes      = $folderCodeMapper->findByColumn('folder_id', $folder->getId(), ['deleted' => true]);
            /** @var \Schoolbox\Resource\Folder\Code\Model $folderCode */
            foreach ($folderCodes as $folderCode) {
                if ($folderCode->isDeleted()) {
                    try {
                        $archivedFolderIds = $folderMapper->findAllByArchivedCode($folderCode->getCode())->getPropertyValues('id');
                    } catch (MapperException $e) {
                        $this->logger->warning("Failed to find folder code: {$folderCode->getCode()}:" . $e->getMessage());
                        continue;
                    }
                    if (in_array($folder->getId(), $archivedFolderIds)) {
                        $folderCodes->add($folderCode);
                    }
                }
            }
        }
        return $folderCodes->getPropertyValues('code');
    }


    /**
     * Determine the query parameters for finding the key of an activity mark.
     *
     * @param array $assessmentRemote
     * @param User  $student
     * @return array
     */
    private function determineActivityMarkKeyParams(array $assessmentRemote, User $student): array
    {
        return [
            'fileType'     => $assessmentRemote['FileType'],
            'fileYear'     => $this->getUserFileYear($student->getExternalId()),
            'fileSemester' => $this->getUserFileSemester($student->getExternalId()),
            'classCampus'  => $assessmentRemote['ClassCampus'],
            'classCode'    => $assessmentRemote['ClassCode'],
            'markbookSeq'  => $assessmentRemote['MarkBookSeq'],
            'externalID'   => $student->getExternalId(),
        ];
    }


    /**
     * Determine the query parameters for inserting or updating the value of an
     * activity mark.
     *
     * @param IFeedback $feedback
     * @return array
     */
    private function determineActivityMarkValueParams(IFeedback $feedback): array
    {
        $activity  = $feedback->getAssessable();
        $marker    = $feedback->getReviewer();

        if ($this->markType === self::HUMAN_READABLE) {
            $markValue = $feedback->getHumanReadableMark();
        } else {
            $mark      = $feedback->getNormalisedMark();
            $markValue = null === $mark ? self::NO_MARK_GIVEN : round($mark, 2);
        }


        $comment   = $feedback->getComment();
        return [
            'mark'             => $markValue,
            'topicComment'     => $comment,
            'resultDate'       => $activity->getDueAt()->getDatabase(),
            'markerExternalId' => $marker->getExternalId(),
        ];
    }


    /**
     * Normalizes a class code to a lower case value.
     *
     * Required as no other class code comparisons within Schoolbox are
     * case-sensitive.
     *
     * @param string $classCode
     * @return string
     */
    private function normalizeClassCode(string $classCode): string
    {
        return mb_strtolower($classCode);
    }


    /**
     * Normalizes a list of class codes to lower case values.
     *
     * Required as no other class code comparisons within Schoolbox are
     * case-sensitive.
     *
     * @param array&string[] $classCodes
     * @param array|null     $campusCodes
     * @return array
     */
    private function normalizeClassCodes(array $classCodes, ?array $campusCodes = null): array
    {
        $normalizedClassCodes = array_map([$this, 'normalizeClassCode'], $classCodes);
        if (!is_array($campusCodes) || count($classCodes) !== count($campusCodes)) {
            return $normalizedClassCodes;
        }
        $codeWithCampus = [];
        for ($i = 0; $i < count($classCodes); $i++) {
            $codeWithCampus[] = "{$campusCodes[$i]}/{$classCodes[$i]}";
        }
        return array_merge(
            $normalizedClassCodes,
            array_map([$this, 'normalizeClassCode',], array_unique($codeWithCampus))
        );
    }

    /**
     * Find role with the role id
     * @param int|null $getId
     * @return \Schoolbox\Role
     * @throws MapperException
     */
    private function getRole(?int $getId)
    {
        if (!isset($this->roles[$getId])) {
            $this->roles[$getId] = \Schoolbox\RoleMapper::getInstance()->findById($getId);
        }
        return $this->roles[$getId];
    }
}
