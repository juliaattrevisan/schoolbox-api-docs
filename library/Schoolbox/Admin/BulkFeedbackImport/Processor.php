<?php

/**
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
     * @var \PDOStatement
     */
    private \PDOStatement $assessmentQuery;


    /**
     * A set of (user id, fullname) tuples to save some mem
     *
     * @var array
     */
    private array $learnerLookup = [];


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

    }


    /**
     * Perform a case-insensitive match on a discriminating column in the imported file, helping us
     * figure out which strategy to use to process this file
     *
     * @param array $headerRow
     * @return IStrategy
     */
    private function produceStrategy(array $headerRow): IStrategy
    {
        $headers = array_map('strtolower', $headerRow);

        if (in_array(strtolower(DueWork::DISCRIMINATING_COLUMN), $headers)) {
            return new DueWork(
                $this->perform,
                $this->assessmentMapperProxy,
                $this->rosettaStone,
                $this->assessmentTitle,
                $this->userMapper,
                $this->submissionBoxMapper,
                $this->returnedWorkMapper
            );
        }

        if (in_array(strtolower(Reporting::DISCRIMINATING_COLUMN), $headers)) {
            return new Reporting(
                $this->perform,
                $this->assessmentMapperProxy,
                $this->rosettaStone,
                $this->assessmentTitle,
                $this->userMapper,
                $this->reportingMapper,
                $this->stateMapper,
                $this->feedbackDataSource,
            );
        }

        throw new \RuntimeException('Unknown feedback importer strategy.');
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

        $strategy = $this->produceStrategy($reader->readHeaderRow());

        /* We're going to reuse this a lot
         */
        $this->assessmentQuery = $this->userMapper->getDatabase()->prepare($strategy->getAssessmentQuery());

        /* A list of warnings/errors/scenarios that schools must fix before we can continue
         */
        $errors = 0;

        /* Load a particular file
         */
        $reader = new Reader($this->filesystemProxy->readStream($file), true);
        $reader->setExpectedColumns($strategy->getExpectedColumns());

        /* Maintain a row counter
         */
        $i = 0;

        /* The current datetime is used for publish_at if it was omitted
         */
        $now = \Alaress_Date_Format::now();

        foreach ($reader as $row) {
            /* Remember the row number
             */
            $i++;

            /* Compute publish date from csv
             * and also make it the same as created_at and updated_at to better keep track
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
                    $this->log(sprintf("Row %s: Invalid date format. Make sure it's correct and try again.", $i), self::WARNING);

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
                        "Row %s: Can't find Class Code. Make sure it's correct and try again.",
                        $i
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

            if (!isset($this->learnerLookup[$externalId])) {
                try {
                    $learner = $this->userMapper->findByExternalId($externalId);

                    $this->learnerLookup[$externalId] = [
                        'id' => $learner->getId(),
                        'fullname' => $learner->getFullname(),
                    ];
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $this->log(sprintf(
                        'Row %s: student %s not found.',
                        $i,
                        $externalId
                    ), IStrategy::WARNING);

                    $errors++;

                    continue;
                }
            }

            /* Yank it from a local cache
             */
            $learner = $this->learnerLookup[$externalId];

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

            foreach ($candidateAssessments as $assessmentData) {
                $strategy->processRow(
                    $i,
                    $folder,
                    $externalId,
                    $learner,
                    $assessmentData,
                    $adf,
                    $row[$strategy->getDiscriminatorColumn()],
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
