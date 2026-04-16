<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\BulkFeedbackImport;

use League\Flysystem\FilesystemException;
use Schoolbox\Admin\BulkFeedbackImport\Strategy\DueWork;
use Schoolbox\Admin\BulkFeedbackImport\Strategy\Reporting;
use Schoolbox\Admin\BulkFeedbackImport\Strategy\IStrategy;
use Schoolbox\Admin\Import\Profile\Reader;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Processor
{
    /**
     * Assume a default time of 1:11pm
     */
    private const PUBLISH_TIME = '13:11:11';


    /**
     * A query to find assessments related to the folder id, work type and assessment title tuple.
     *
     * It joins onto subobject tables because multiple assessments with the same work type and title might exist for
     * assessments that have different sub objects.
     *
     * Used to find a relationship between a folder and a particular assessment by its (assessment title, worktype) combo
     */
    private const string QUERY_ASSESSMENT_TITLE_WORKTYPE = "
        SELECT
            a.id AS assessment_id,
            a.title AS assessment_title,
            sb.id AS box_id,
            r.id AS reporting_id
        FROM mv_assessment_relation mvar
        INNER JOIN assessment a ON a.id = mvar.assessment_id
        LEFT JOIN submission_box sb ON sb.assessment_id = a.id
        LEFT JOIN reporting r ON r.assessment_id = a.id
        INNER JOIN folder f ON mvar.folder_id = f.id
        WHERE mvar.folder_id = :folderId
        AND a.work_type_id = :workTypeId
        AND a.title = :assessmentTitle
        AND a.deleted_at IS NULL
        AND f.deleted_at IS NULL
    ";


    /**
     * @var \PDOStatement
     */
    private \PDOStatement $assessmentQuery;


    /**
     * A set of (folder code, folder id, folder title) tuples to save some mem
     *
     * @var array
     */
    private array $folderLookup = [];


    /**
     * A list of log messages that we can report to users
     *
     * @var array
     */
    private array $messages = [];


    /**
     * This is used to save feedbacks into Due Work
     *
     * @var IStrategy
     */
    private IStrategy $dueWorkStrategy;


    /**
     * This is used to save feedbacks into Reporting
     *
     * @var IStrategy
     */
    private IStrategy $reportingStrategy;


    /**
     * Constructor
     *
     * The submissionBoxMapper is used to craft a returned work model so that its corresponding mapper
     * can create Participant and Participant Plan records for feedbacks
     *
     * The returnedWorkMapper is used to create Participant and Participant Plan records for a feedback
     * via its post-save hooks
     *
     * @param MapperProxy $assessmentMapperProxy
     * @param FilesystemProxy $filesystemProxy
     * @param UserMapper $userMapper
     * @param Mapper $folderMapper
     * @param SubmissionBoxMapper $submissionBoxMapper
     * @param ReturnedWorkMapper $returnedWorkMapper
     * @param \Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper $reportingMapper
     * @param StateMapper $stateMapper
     * @param IFeedbackDataSource $feedbackDataSource
     * @param RosettaStone $rosettaStone
     * @param string $dateLocal
     * @param string $assessmentTitle
     * @param \Schoolbox\Learning\Assessment\WorkType\Model $workType
     * @param bool $perform
     */
    public function __construct(
        private MapperProxy $assessmentMapperProxy,
        private FilesystemProxy $filesystemProxy,
        private \Schoolbox\UserMapper $userMapper,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
        private SubmissionBoxMapper $submissionBoxMapper,
        private ReturnedWorkMapper $returnedWorkMapper,
        private \Schoolbox\Learning\Assessment\DataModel\Reporting\Mapper $reportingMapper,
        private StateMapper $stateMapper,
        private IFeedbackDataSource $feedbackDataSource,
        private RosettaStone $rosettaStone,
        private string $dateLocal,
        private string $assessmentTitle,
        private \Schoolbox\Learning\Assessment\WorkType\Model $workType,
        private bool $perform
    ) {
        $this->dueWorkStrategy = new DueWork(
            $this->perform,
            $this->rosettaStone,
            $this->assessmentTitle,
            $this->submissionBoxMapper,
            $this->returnedWorkMapper
        );

        $this->reportingStrategy = new Reporting(
            $this->perform,
            $this->rosettaStone,
            $this->assessmentTitle,
            $this->reportingMapper,
            $this->feedbackDataSource,
        );
    }


    /**
     * Process feedbacks
     *
     * @param Model $file
     * @param User $creator
     * @return array
     * @throws FilesystemException
     * @throws Exception
     * @throws Deleted
     */
    public function parseFeedbacks(
        IStorage $file,
        \Schoolbox\User $creator,
    ): array {
        $reader = new Reader($this->filesystemProxy->readStream($file), true);
        $headerRow = $reader->readHeaderRow();

        /* A list of warnings/errors/scenarios that schools must fix before we can continue
         */
        $errors = 0;

        /* Load a particular file
         */
        $reader = new Reader($this->filesystemProxy->readStream($file), true);
        $reader->setExpectedColumns($this->selectCsvStrategy($headerRow));

        /* Maintain a row counter
         */
        $i = 0;

        /* The current datetime is used for publish_at if it was omitted
         */
        $now = \Alaress_Date_Format::now();

        /* We're going to reuse this a lot
         */
        $this->assessmentQuery = $this->userMapper->getDatabase()->prepare(self::QUERY_ASSESSMENT_TITLE_WORKTYPE);

        foreach ($reader as $row) {
            /* Remember the row number in the csv, starting at 1
             * This is used to log errors to help humans locate them in the csv itself
             */
            $i++;

            /* Compute publish date from csv and also make it the same as created_at and updated_at to better keep track
             * of things after an import has occurred
             */
            $datetime = trim((string)$row['Publish Date']);

            if ('' === $datetime) {
                $adf = $now;
            } else {
                /* Check the format of the date: try both database and local short
                 */
                try {
                    $combined = sprintf('%s %s', $datetime, self::PUBLISH_TIME);
                    $format = $this->dateLocal . ' H:i:s';

                    $adf = new \Alaress_Date_Format($combined, $format);
                } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                    $this->log(sprintf("Row %s: Invalid date format. Make sure it's correct and try again.", $i), IStrategy::WARNING);

                    $errors++;

                    continue;
                }
            }

            /* Process feedbacks
             */
            $folderCode = trim((string)$row['Class Code']);

            /* Assume this exists, I guess, for now
             */
            if (!isset($this->folderLookup[$folderCode])) {
                try {
                    $folder = $this->folderMapper->findByCode($folderCode);

                    $this->folderLookup[$folderCode] = [
                        'id' => $folder->getId(),
                        'title' => $folder->getTitle()
                    ];
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $this->log(sprintf(
                        "Row %s: Can't find Class Code \"%s\". Make sure it's correct and try again.",
                        $i,
                        $folderCode
                    ), IStrategy::WARNING);

                    $errors++;

                    continue;
                }
            }

            /* Yank it from a local cache
             */
            $folder = $this->folderLookup[$folderCode];

            /* Avengers Assemble
             */

            /* First see if this student exists
             */
            $externalId = trim((string)$row['Student External ID']);

            try {
                $learner = $this->userMapper->findByExternalId($externalId);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->log(sprintf(
                    'Row %s: student %s not found.',
                    $i,
                    $externalId
                ), IStrategy::WARNING);

                $errors++;

                continue;
            }

            /* Then find the assessment's corresponding submission box id
             */
            $this->assessmentQuery->execute([
                'assessmentTitle' => $this->assessmentTitle,
                'workTypeId' => $this->workType->getId(),
                'folderId' => $folder['id']
            ]);

            /* There's only meant to be one assessment that matches the (title, worktype, folder) tuple.
             * Multiple assessments that match means we can't determine where feedback is meant to go.
             * We need the schools to resolve the conflicting assessments.
             *
             * There can be only be one, highlander.
             */
            $candidateAssessments = $this->assessmentQuery->fetchAll(\PDO::FETCH_ASSOC);

            if (\count($candidateAssessments) > 1) {
                $this->log(sprintf(
                    'Row %s: multiple %s found for %s "%s" in folder "%s" (%s) using Work Type "%s" (%s).
                    Please ensure %s are uniquely identifiable before continuing.',
                    $i,
                    $this->rosettaStone->translate('activity', true),
                    $this->rosettaStone->translate('activity', false),
                    $this->rosettaStone->translate('activity', true),
                    $this->assessmentTitle,
                    $folder['title'],
                    $folder['id'],
                    $this->workType->getName(),
                    $this->workType->getId()
                ), IStrategy::WARNING);

                $errors++;

                continue;
            }

            /* No assessments found
             */
            if (0 === \count($candidateAssessments)) {
                $this->log(sprintf(
                    'Row %s: no %s found for "%s" in folder "%s" (%s) using Work Type "%s" (%s).',
                    $i,
                    $this->rosettaStone->translate('activity', true),
                    $this->assessmentTitle,
                    $folder['title'],
                    $folder['id'],
                    $this->workType->getName(),
                    $this->workType->getId()
                ), IStrategy::WARNING);

                $errors++;

                continue;
            }

            /* Use each strat's processRow() method to interpret how to put this data into a feedback
             */
            foreach ($candidateAssessments as $assessmentData) {
                /* For some reason we don't an assessment identifier; that's pretty catastrophic
                 * This shouldn't ever be the case
                 */
                if (null === $assessmentData['assessment_id']) {
                    $this->log(sprintf(
                        'Row %s: Unknown assessment; could not find an id.',
                        $i,
                    ), IStrategy::WARNING);

                    $errors++;

                    continue;
                }

                /* Select a strategy to process feedbacks at runtime: due work and reporting save in different places
                 * using different structures
                 */
                $strategy = $this->selectFeedbackStrategy($assessmentData);

                /* Try to spawn an IAssessable instance
                 *
                 * Note that this is horribly non-performant since it requires the assessmentMapperProxy
                 * to do enormous amounts of heavy lifting: you could shortcut this by maybe spawning stubs
                 * containing only the Mark Scheme chosen by the assessment...
                 */
                /** @var \Schoolbox\Learning\Assessment\Reporting\Model $assessment */
                $assessment = $this->assessmentMapperProxy->findById($assessmentData['assessment_id']);

                /* Interpret the csv structure to figure out where marks come from and how to derive all three pieces
                 */
                $vals = $this->calculateMarks($assessment, $headerRow, $row);

                $strategy->processRow(
                    $i,
                    $folder,
                    $learner,
                    $assessment,
                    $assessmentData,
                    $adf,
                    $vals['normalisedMark'],
                    $vals['learnerHumanReadableMark'],
                    $vals['instructorHumanReadableMark'],
                    $row['Comment'],
                    $creator
                );
            }
        }

        return [
            'messages' => array_merge($this->messages, $strategy->getMessages()),
            'errors' => $errors + $strategy->getErrorCount(),
            'links' => $strategy->getLinks()
        ];
    }


    /**
     * Based on the normalised/human readable columns, figure out which Expected Columns to process
     *
     * @param array $headerRow
     * @return array
     */
    private function selectCsvStrategy(array $headerRow): array
    {
        if (in_array('Normalised Mark', $headerRow)) {
            return [
                'Class Code',
                'Student External ID',
                'Normalised Mark',
                'Comment',
                'Publish Date'
            ];
        }

        if (in_array('Human Readable Grade', $headerRow)) {
            return [
                'Class Code',
                'Student External ID',
                'Human Readable Grade',
                'Comment',
                'Publish Date'
            ];
        }

        throw new \RuntimeException('Unknown csv importer strategy.');
    }


    /**
     * Based on the assessment identifiers, figure out which feedback-saving strategy to use.
     *
     * @param array $assessmentData
     * @return IStrategy
     */
    private function selectFeedbackStrategy(array $assessmentData): IStrategy
    {
        if (null !== $assessmentData[$this->dueWorkStrategy->getDiscriminatorColumn()]) {
            return $this->dueWorkStrategy;
        }

        if (null !== $assessmentData[$this->reportingStrategy->getDiscriminatorColumn()]) {
            return $this->reportingStrategy;
        }

        throw new \RuntimeException('Unknown feedback importer strategy.');
    }


    /**
     * One of the columns "Normalised Mark" and "Human Readable Grade" exists with a meaningful value.
     * From a row in the csv, figure how to calculate the three values.
     *    - normalised mark
     *    - learner human readable mark
     *    - instructor human readable mark
     *
     * @param IAssessable $assessment
     * @param array $headerRow
     * @param array $row
     * @return array
     */
    private function calculateMarks(IAssessable $assessment, array $headerRow, array $row): array
    {
        $normalisedMarkColumn = 'Normalised Mark';
        $humanReadableGradeColumn = 'Human Readable Grade';

        $normalisedMark = null;
        $learnerHumanReadableMark = null;
        $instructorHumanReadableMark = null;

        /** Get mark schemes
         * - Instructor mark scheme is used to normalise what was inputted by the user
         * - Learner mark scheme is used when displaying the mark back to learners/parents
         */
        $instructorScheme = $assessment->getInstructorMarkScheme();
        $learnerScheme = $assessment->getMarkScheme();

        /* If a Normalised Mark column exists in the header, derive Human Readable marks from Normalised Mark.
         */
        if (in_array($normalisedMarkColumn, $headerRow)) {
            $normalisedMark = (float)trim((string)$row[$normalisedMarkColumn]);

            /* This is what the student would see
             */
            $learnerHumanReadableMark = $assessment->getMarkScheme()->getHumanReadableMark($normalisedMark);

            /* This is what the instructor would see, due to SA-18452
             * Disgustingly typehint this getInstructorMarkScheme() method which is unique to two activities atm
             */
            /** @var \Schoolbox\Learning\Assessment\DueWork\Model|\Schoolbox\Learning\Assessment\Reporting\Model $assessment */
            $instructorHumanReadableMark = $assessment->getInstructorMarkScheme()->getHumanReadableMark($normalisedMark);

            /* TODO restore error logging
             */
//            if ($normalisedMark < 0 || $normalisedMark > 100) {
//                $this->log(sprintf(
//                    'Row %s: normalised mark must be between 0 and 100 for %s "%s"',
//                    $i,
//                    $this->rosettaStone->translate('activity', false),
//                    $this->assessmentTitle,
//                ), IStrategy::WARNING);
//
//                /* Returns after an increment of errors
//                 */
//                $this->errorCount++;
//
//                return;
//            }

            /* Apparently this is how you detect a Submission State: an exception is thrown
             * trying to interpret it as one of the Mark Schemes. No wonder it was classified
             * as the $errors var. Wild!
             *
             * Shanked from Learning/Assessment/DueWork/Feedback/Factory.php::210
             */
            try {
                /* Find out what the learner's version would have been and save that too
                 * Arguably you could skip this derivation, only doing it if $instructor scheme was different to $learner scheme
                 */
                $learnerHumanReadableMark = $learnerScheme->getHumanReadableMark($normalisedMark);
            } catch (\InvalidArgumentException $e) {
                if ('' !== $instructorHumanReadableMark) {
                    /* If it's a Submission State, save the text as the human readable mark
                     * and derive its normalised mark from the uh Lists in admin
                     *
                     * If this fails, presume it's a freeform text and just save a human readable mark only
                     */
                    try {
                        $code = $this->stateMapper->findOneByColumn('display_as', $instructorHumanReadableMark);

                        $normalisedMark = $code->getNormaliseAs();

                        /* There isn't a learner-specific Submission State: it's the same for both parties.
                         */
                        $learnerHumanReadableMark = $instructorHumanReadableMark;
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        /* Freeform text; learners see the same thing as instructors and there's no normalised mark
                         */
                        $normalisedMark = null;
                        $learnerHumanReadableMark = $instructorHumanReadableMark;
                    }
                }
            }

            /* Compile all three data points into a tuple that processRow can use
             */
            return [
                'normalisedMark' => $normalisedMark,
                'learnerHumanReadableMark' => $learnerHumanReadableMark,
                'instructorHumanReadableMark' => $instructorHumanReadableMark,
            ];
        }

        /* If a Human Readable Grade column exists in the header, derive Normalised Mark from Human Readable Grade.
         */
        if (in_array($humanReadableGradeColumn, $headerRow)) {
            $instructorHumanReadableMark = trim((string)$row[$humanReadableGradeColumn]);

            /* Apparently this is how you detect a Submission State: an exception is thrown
             * trying to interpret it as one of the Mark Schemes. No wonder it was classified
             * as the $errors var. Wild!
             *
             * Shanked from Learning/Assessment/DueWork/Feedback/Factory.php::210
             */
            try {
                $normalisedMark = $instructorScheme->getNormalisedMark($instructorHumanReadableMark);

                /* Find out what the learner's version would have been and save that too
                 * Arguably you could skip this derivation, only doing it if $instructor scheme was different to $learner scheme
                 */
                $learnerHumanReadableMark = $learnerScheme->getHumanReadableMark($normalisedMark);
            } catch (\InvalidArgumentException $e) {
                if ('' !== $instructorHumanReadableMark) {
                    /* If it's a Submission State, save the text as the human readable mark
                     * and derive its normalised mark from the uh Lists in admin
                     *
                     * If this fails, presume it's a freeform text and just save a human readable mark only
                     */
                    try {
                        $code = $this->stateMapper->findOneByColumn('display_as', $instructorHumanReadableMark);

                        $normalisedMark = $code->getNormaliseAs();

                        /* There isn't a learner-specific Submission State: it's the same for both parties.
                         */
                        $learnerHumanReadableMark = $instructorHumanReadableMark;
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        /* Freeform text; learners see the same thing as instructors and there's no normalised mark
                         */
                        $normalisedMark = null;
                        $learnerHumanReadableMark = $instructorHumanReadableMark;
                    }
                }
            }

            /* Compile all three data points into a tuple that processRow can use
             */
            return [
                'normalisedMark' => $normalisedMark,
                'learnerHumanReadableMark' => $learnerHumanReadableMark,
                'instructorHumanReadableMark' => $instructorHumanReadableMark,
            ];
        }

        /* We should never be here, but you never know: they could upload a totally random csv
         */
        throw new \RuntimeException('Unknown csv; could not process row');
    }


    /**
     * Log messages to multiple places
     *
     * @param string $message
     * @param string $type
     * @return void
     */
    private function log(string $message, string $type): void
    {
        $this->messages[] = [
            'message' => $message,
            'type' => $type,
        ];
    }
}
