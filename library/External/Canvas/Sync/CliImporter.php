<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database;
use Alaress\Jitter\Jitter;
use Alaress\Random\FloatGenerator;
use Alaress_Byte_Format;
use External\Canvas\Importer\CanvasDb;
use External_ClassInterface;
use External_UserInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeLevelGroupMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Mapper as GradeLevelMapper;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Exception;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Quiz;
use Schoolbox\Learning\Quiz\Section as QuizSection;
use Schoolbox\Learning\Quiz\Section\Question as QuizQuestion;
use Schoolbox\Learning\Quiz\Section\Question\QuestionType;
use Schoolbox\Learning\Quiz\Section\Question\Solution as QuizSolution;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use RuntimeException;
use Schoolbox\MapperFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;

use function file_exists;
use function floor;
use function fopen;
use function json_decode;
use function memory_get_peak_usage;
use function microtime;
use function sprintf;

/**
 * Command line application for syncing canvas-data
 * fetch
 * stage
 * sync
 */
class CliImporter extends CliTool\App
{
    private const OPT_SHORT_HELP       = 'h';           // print help
    private const OPT_LONG_HELP        = 'help';        // print help
    private const OPT_LONG_SKIP_FETCH  = 'skip-fetch';  // Don't re-fetch files from Canvas API, use previously downloaded
    private const OPT_LONG_SKIP_IMPORT = 'skip-import'; // Don't re-import files into staging DB
    private const OPT_LONG_SKIP_SYNC   = 'skip-sync';   // Don't sync from staging DB to schoolbox DB

    // Canvas tables to import
    /**
     * @deprecated
     * TODO: OLD CANVAS DATA - REMOVE THIS IN V24
     */
    private const TABLES_TO_IMPORT_DEPRECATED = [
        'account_dim',
        'assignment_dim',
        'assignment_group_dim',
        'assignment_override_dim',
        'assignment_override_user_dim',
        'course_dim',
        'course_section_dim',
        'enrollment_dim',
        'enrollment_term_dim',
        'group_dim',
        'group_membership_fact',
        'pseudonym_dim',
        'quiz_dim',
        'quiz_fact',
        'submission_dim',
        'submission_fact',
        'submission_comment_dim',
        'quiz_question_answer_dim',
        'quiz_question_answer_fact',
        'quiz_question_dim',
        'quiz_question_fact',
        'quiz_question_group_dim',
        'quiz_question_group_fact',
        'quiz_submission_dim',
        'quiz_submission_fact',
        'quiz_submission_historical_dim',
        'quiz_submission_historical_fact',
    ];

    private const TABLES_TO_IMPORT = [
        "accounts",
        "assignment_groups",
        "assignment_override_students",
        "assignment_overrides",
        "assignments",
        "course_account_associations",
        "course_sections",
        "courses",
        "enrollment_dates_overrides",
        "enrollment_states",
        "enrollment_terms",
        "enrollments",
        "group_memberships",
        "groups",
        "pseudonyms",
        "quiz_groups",
        "quiz_submissions",
        "quizzes",
        "submission_comments",
        "submission_versions",
        "submissions",
        "users",
    ];


    /**
     * Guzzle requests consts
     */
    /**
     * Request timeout
     */
    private const DEFAULT_TIMEOUT = 300;

    /**
     * Number of seconds to take a nap before retrying a failed endpoint
     *
     * Sometimes endpoints fail for no obvious reason and trying again seems
     * to work - instead of doing it manually, try insisting programmatically
     * waiting NAP_TIMEOUT seconds between each attempt
     */
    private const ATTEMPT_BASE_TIMEOUT = 3;

    /**
     * Number of times to retry after having NAP_TIMEOUT seconds' rest before
     * finally giving up
     */
    private const MAX_ATTEMPTS = 3;


    /** @inheritdoc */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
        ];
    }


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_SKIP_FETCH,
            self::OPT_LONG_SKIP_IMPORT,
            self::OPT_LONG_SKIP_SYNC,
        ];
    }


    /**
     * @inheritDoc
     * @throws ContainerExceptionInterface
     * @throws Exception
     * @throws Mapper\Exception
     * @throws NotFoundExceptionInterface
     */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        if ($this->perform()) {
            $this->getLogger()->notice('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->getLogger()->notice('Running in dry-run mode: use -p to write changes to database');
        }

        // Check Canvas URL config
        if (!$this->getConfig()->get(ConfigMapper::_CANVAS_URL)) {
            $this->getLogger()->warning('Canvas URL is not set.');
        }

        // Ensure we can connect to canvas API + staging DB prior to executing any actions.
        $canvasDb = $this->buildCanvasDbConnectionOrExit();
        // Check api config
        if (
            $this->getConfig()->get(ConfigMapper::_CANVAS_DATA2_SECRET)
            && $this->getConfig()->get(ConfigMapper::_CANVAS_DATA2_CLIENT_ID)
        ) {
            $canvasApi = new Data2Connector(
                $this->getConfig()->get(ConfigMapper::_CANVAS_DATA2_SECRET),
                $this->getConfig()->get(ConfigMapper::_CANVAS_DATA2_CLIENT_ID),
                $this->getLogger()
            );
            $tempDir   = $this->createTempDir('canvas-data2');
            // Download data files
            if ($this->getOpt(self::OPT_LONG_SKIP_FETCH)) {
                $this->getLogger()->notice("File download skipped");
            } else {
                $canvasApi->fetchCanvasDataFilesFromApi(self::TABLES_TO_IMPORT, $tempDir, $this->perform());
            }
            $dataRestorer = new Data2Restorer(
                $canvasDb,
                $this->getLogger(),
                $this->perform(),
                self::TABLES_TO_IMPORT,
                [],
                $canvasApi,
            );
            if (!$this->getOpt(self::OPT_LONG_SKIP_IMPORT)) {
                $this->getLogger()->notice("Importing schema from json file.");
                $dataRestorer->restoreSchema();
            }

            $this->loadCanvasDataFilesToStagingDatabase($tempDir, $dataRestorer, self::TABLES_TO_IMPORT, true);
        } elseif (
            $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_KEY)
            && $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_SECRET)
        ) {
            /**
             * @deprecated
             * TODO: OLD CANVAS DATA - REMOVE THIS IN V24
             */
            $canvasApi = new Connector(
                $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_KEY),
                $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_SECRET),
                $this->getLogger(),
                $this->perform()
            );
            $tempDir   = $this->createTempDir('canvas-data');

            $schemaFile = $tempDir . '/schema.json';

            // Execute tool actions.
            if ($this->getOpt(self::OPT_LONG_SKIP_FETCH)) {
                $this->getLogger()->notice("File download skipped");
            } else {
                $dataFiles = $canvasApi->fetchCanvasDataFilesFromApi($tempDir, $schemaFile);
                $this->downloadDataFiles($dataFiles, $tempDir, self::TABLES_TO_IMPORT_DEPRECATED);
            }
            // Create schema in the staging database
            $schemaContainer = json_decode(file_get_contents($schemaFile), true);
            $dataRestorer    = new DataRestorer(
                $canvasDb,
                $this->getLogger(),
                $this->perform(),
                self::TABLES_TO_IMPORT_DEPRECATED,
                $schemaContainer['schema']
            );

            if (!$this->getOpt(self::OPT_LONG_SKIP_IMPORT)) {
                $this->getLogger()->notice("Importing schema from json file.");
                $dataRestorer->restoreSchema();
            }

            $this->loadCanvasDataFilesToStagingDatabase($tempDir, $dataRestorer, self::TABLES_TO_IMPORT_DEPRECATED, false);
        } else {
            $this->getLogger()->warning('API Key or secret is not set.');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->syncStagingDataWithSchoolbox($canvasDb);
        $this->logStats();
    }


    /**
     * Builds and returns a Canvas DB connection if possible, otherwise exits
     *
     * @return CanvasDb
     */
    private function buildCanvasDbConnectionOrExit(): CanvasDb
    {
        try {
            $canvasDb = new CanvasDb($this->getConfig());
            $canvasDb->getConnector();
            return $canvasDb;
        } catch (Database\Exception $e) {
            $this->getLogger()->error(
                sprintf(
                    'Unable to connect to database %s: %s. Use tools/canvas/create_database.php to create database.',
                    'canvas_data2_' . INSTANCE_ID,
                    $e->getMessage()
                )
            );
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
    }


    /**
     * Processes local files to build schema and data in local canvas staging database
     *
     * @param string       $tempDir
     * @param DataRestorer $dataRestorer
     * @param array        $tablesToImport
     * @param bool         $ignoreFirstLine Whether the first row of the data file is header
     */
    private function loadCanvasDataFilesToStagingDatabase(
        string $tempDir,
        DataRestorer $dataRestorer,
        array $tablesToImport,
        bool $ignoreFirstLine
    ): void {
        if ($this->getOpt(self::OPT_LONG_SKIP_IMPORT)) {
            $this->getLogger()->notice("File import to staging database skipped");
            return;
        }

        // Store downloaded data to tables in the staging database
        $this->getLogger()->notice('Restoring data to tables.');
        $tableGzFiles = scandir($tempDir);
        if ($tableGzFiles === false || !count($tableGzFiles)) {
            $this->getLogger()->warning("Failed to open directory $tempDir.");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        // Which table are already seen (i.e. truncated)
        $truncatedTables = [];
        foreach ($tableGzFiles as $tableGzFile) {
            if ($tableGzFile === '..' || $tableGzFile === '.') {
                continue;
            }
            // Table file name format should be <table name>.<index>.gz
            $tableFileParts = explode('.', $tableGzFile);
            if (count($tableFileParts) === 3) {
                // <table name>.<index>.gz
                $tableName = $tableFileParts[0];
                $index     = (int)$tableFileParts[1];
                if (!in_array($tableName, $tablesToImport)) {
                    continue;
                }
                $tableFile = "$tempDir/$tableName.$index";
            } else {
                continue;
            }
            $this->getLogger()->debug("Loading $tableFile.");

            exec("gunzip -f -k " . escapeshellarg("$tempDir/$tableGzFile")); // force overwrite of existing files, keep original
            $dataRestorer->restoreTableData($tableName, $tableFile, !in_array($tableName, $truncatedTables), $ignoreFirstLine);
            $truncatedTables[] = $tableName;
            if (file_exists($tableFile)) {
                unlink($tableFile);
            } else {
                $this->getLogger()->debug(
                    sprintf(
                        'Could not unlink %s; file does not exist.',
                        $tableFile
                    )
                );
            }
        }
        $dataRestorer->addExtraIndexes();
    }


    /**
     * Syncs data from canvas staging db to schoolbox db
     *
     * @param CanvasDb $canvasDb
     * @throws Mapper\Exception
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function syncStagingDataWithSchoolbox(CanvasDb $canvasDb): void
    {
        if ($this->getOpt(self::OPT_LONG_SKIP_SYNC)) {
            $this->getLogger()->notice("Sync from staging to schoolbox database skipped");
            return;
        }

        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        /** @var External_ClassInterface $externalClass */
        $externalClass = $this->getDi()->get('externalClass');
        /** @var External_UserInterface $externalUser */
        $externalUser = $this->getDi()->get('externalUser');
        /** @var MarkTypeMapper $markTypeMapper */
        $markTypeMapper = $mapperFactory->get(MarkTypeMapper::class);
        /** @var GradeLevelGroupMapper $gradeLevelGroupMapper */
        $gradeLevelGroupMapper = $mapperFactory->get(GradeLevelGroupMapper::class);
        /** @var GradeLevelMapper $gradeLevelMapper */
        $gradeLevelMapper = $mapperFactory->get(GradeLevelMapper::class);
        /** @var WorkType\Mapper $workTypeMapper */
        $workTypeMapper = $mapperFactory->get(WorkType\Mapper::class);
        /** @var Quiz\Mapper $quizMapper */
        $quizMapper = $mapperFactory->get(Quiz\Mapper::class);
        /** @var QuizSection\Mapper $quizSectionMapper */
        $quizSectionMapper = $mapperFactory->get(QuizSection\Mapper::class);
        /** @var QuizQuestion\Mapper $quizQuestionMapper */
        $quizQuestionMapper = $mapperFactory->get(QuizQuestion\Mapper::class);
        /** @var QuestionType\Mapper $quizQuestionTypeMapper */
        $quizQuestionTypeMapper = $mapperFactory->get(QuestionType\Mapper::class);
        /** @var QuizSolution\Mapper $quizSolutionMapper */
        $quizSolutionMapper = $mapperFactory->get(QuizSolution\Mapper::class);
        /** @var Quiz\Attempt\Mapper $quizAttemptMapper */
        $quizAttemptMapper = $mapperFactory->get(Quiz\Attempt\Mapper::class);
        /** @var Quiz\Attempt\Answer\Mapper $quizAnswerMapper */
        $quizAnswerMapper = $mapperFactory->get(Quiz\Attempt\Answer\Mapper::class);
        /** @var SubmissionBoxMapper $submissionBoxMapper */
        $submissionBoxMapper = $mapperFactory->get(SubmissionBoxMapper::class);
        /** @var Assessment\Mapper $assessmentMapper */
        $assessmentMapper  = $mapperFactory->get(Assessment\Mapper::class);
        $planMapper        = $mapperFactory->get(Participant\Plan\Mapper::class);
        $participantMapper = $mapperFactory->get(Participant\Mapper::class);
        /** @var UserMapper $userMapper */
        $userMapper = $mapperFactory->get(UserMapper::class);
        /** @var Folder\Mapper $folderMapper */
        $folderMapper = $mapperFactory->get(Folder\Mapper::class);
        // we are cleaning up folders, we should set the user object for the mapper as alaress, so we know who deleted things
        $folderMapper->setSessionData(['user' => $userMapper->findByUsername(User::USERNAME_SCHOOLBOX_ADMIN)]);
        /** @var CourseImpl\Mapper $courseImplMapper */
        $courseImplMapper = $mapperFactory->get(CourseImpl\Mapper::class);
        $courseSpecMapper = $mapperFactory->get(CourseSpec\Mapper::class);
        /** @var UnitImpl\Mapper $unitImplMapper */
        $unitImplMapper     = $mapperFactory->get(UnitImpl\Mapper::class);
        $unitSpecMapper     = $mapperFactory->get(UnitSpec\Mapper::class);
        $returnedWorkMapper = $mapperFactory->get(ReturnedWorkMapper::class);
        /** @var Assessment\MapperProxy $assessmentDataSource */
        $assessmentDataSource = $this->getDi()->get('assessmentDataSource');
        /** @var IConfigLoader $config */
        $config = $this->getDi()->get('config');

        $dataSync = new DataSync(
            $canvasDb,
            $this->getDatabaseInternal(),
            $externalClass,
            $externalUser,
            $markTypeMapper,
            $gradeLevelGroupMapper,
            $gradeLevelMapper,
            $workTypeMapper,
            $quizMapper,
            $quizSectionMapper,
            $quizQuestionMapper,
            $quizQuestionTypeMapper,
            $quizSolutionMapper,
            $quizAttemptMapper,
            $quizAnswerMapper,
            $submissionBoxMapper,
            $returnedWorkMapper,
            $assessmentDataSource,
            $assessmentMapper,
            $planMapper,
            $participantMapper,
            $folderMapper,
            $userMapper,
            $courseImplMapper,
            $courseSpecMapper,
            $unitImplMapper,
            $unitSpecMapper,
            $config,
            $this->perform(),
            $this->getLogger()
        );
        $dataSync->synchronise();
    }


    /**
     * Creates a temp directory to store files and return its name
     *
     * @param string $name
     * @return string
     */
    private function createTempDir(string $name): string
    {
        $tempDir = $this->getConfig()->get('zip_dir') . '/' . $name;
        if (!is_dir($tempDir) && !mkdir($tempDir) && !is_dir($tempDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
        }
        // remove existing files
        $files = glob($tempDir);
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return $tempDir;
    }


    /**
     * Downloads data files
     *
     * @param array<string, array> $tableList table name => ['files' => [index => ['url' => url]]]
     * @param string               $tempDir
     * @param array                $tablesToImport
     */
    private function downloadDataFiles(array $tableList, string $tempDir, array $tablesToImport): void
    {

        $promises = [];
        $jitter     = new Jitter(new FloatGenerator(), self::MAX_ATTEMPTS, self::ATTEMPT_BASE_TIMEOUT);
        $logger = $this->getLogger();
        $client = new Client([
                                 'timeout' => self::DEFAULT_TIMEOUT,
                             ]);

        foreach ($tableList as $tableName => $tableData) {
            if (!in_array($tableName, $tablesToImport) || !count($tableData['files'])) {
                continue;
            }
            // there can be multiple files
            $tableFiles = $tableData['files'];
            foreach ($tableFiles as $index => $tableFile) {
                $fileName      = "$tempDir/$tableName.$index.gz";
                $tableDataFile = fopen(($this->perform() || !file_exists($fileName)) ? $fileName : '/dev/null', 'wb');
                $this->getLogger()->debug("Downloading $tableName to $fileName");

                $promise = $client->getAsync($tableFile['url'], ['sink' => $tableDataFile]);

                /* Any exception thrown by guzzle might as well be a reason to nap and retry
                */
                $promise->then(
                    null,
                    function (RequestException $e) use ($jitter, $fileName, $logger, $promise) {
                        // retry download...
                        $attempt = 0;
                        $wait = (int)$jitter->calculateJitter($attempt);
                        $logger->warning("Failed to download $fileName, attempt: $attempt: sleeping: $wait");
                        usleep($wait * 1000);
                        return $promise;
                    }
                )->then(
                    null,
                    function (RequestException $e) use ($jitter, $fileName, $logger, $promise) {
                        // retry download...
                        $attempt = 1;
                        $wait = (int)$jitter->calculateJitter($attempt);
                        $logger->warning("Failed to download $fileName, attempt: $attempt: sleeping: $wait");
                        usleep($wait * 1000);
                        return $promise;
                    }
                )->then(
                    null,
                    function (RequestException $e) use ($jitter, $fileName, $logger, $promise) {
                        // retry download...
                        $attempt = 2;
                        $wait = (int)$jitter->calculateJitter($attempt);
                        $logger->warning("Failed to download $fileName, attempt: $attempt: sleeping: $wait");
                        usleep($wait * 1000);
                        return $promise;
                    }
                )->then(
                    null,
                    function (RequestException $e) use ($fileName): void {
                        // epic fail, just exit...
                        // If fallen through to here, there was an error
                        throw new \Schoolbox\Application\Exception\Exception(
                            sprintf("Failed to download %s: #3 times. Error:%s", $fileName, $e->getMessage())
                        );
                    }
                );
                $promises[] = $promise;
            }
        }

        // Now wait until all the results are retrieved
        $bulkPromise = \GuzzleHttp\Promise\Utils::all($promises);
        $result      = $bulkPromise->wait();
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
    {
        $options = [
            [self::OPT_SHORT_HELP, self::OPT_LONG_HELP, 'Print this help and exit'],
            [
                CliTool\Kernel::OPT_SHORT_PERFORM,
                CliTool\Kernel::OPT_LONG_PERFORM,
                'Persist changes to local files, staging DB + Schoolbox DB',
            ],
            [
                null,
                self::OPT_LONG_SKIP_FETCH,
                "If set, use most recently downloaded files, rather that fetching from API",
            ],
            [null, self::OPT_LONG_SKIP_IMPORT, "If set, use most recent state of staging db, don't re-import files"],
            [null, self::OPT_LONG_SKIP_SYNC, "If set, don't sync staging data with schoolbox data"],
        ];

        $green  = self::COLOUR_GREEN;
        $white  = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        $help = "$white

WHAT THIS DOES:
    Downloads schema and data using Canvas API and populates the local canvas staging database named canvas_data_[INSTANCE_ID].
    This script expects the database canvas_data_[INSTANCE_ID] already be created.
    To create the database, use tools/canvas/create_database.php.

SYNOPSIS:
    $green{$this->getToolFilename()} {$yellow}[OPTION]...$white

OPTIONS:";
        foreach ($options as $option) {
            $short     = $option[0] ? "-{$option[0]}" : '';
            $long      = $option[1] ? "--{$option[1]}" : '';
            $shortLong = implode(', ', array_filter([rtrim($short, ':'), rtrim($long, ':')]));
            $help      .= "
    $yellow{$shortLong}$white
        $option[2]

";
        }
        \fwrite(STDOUT, $help);
    }


    /**
     * Log some stats about the script execution
     */
    private function logStats(): void
    {
        $start = $_SERVER['REQUEST_TIME_FLOAT'] ?? $_SERVER['REQUEST_TIME'] ?? null;
        if ($start === null) {
            $this->getLogger()->warning('Unable to determine script start time');
        } else {
            $duration = (microtime(true) - $start);
            $hours    = floor($duration / 3600);
            $minutes  = floor(($duration - $hours * 3600) / 60);
            $seconds  = $duration - $hours * 3600 - $minutes * 60;
            $this->getLogger()->notice(sprintf("Script duration: %02d:%02d:%02d", $hours, $minutes, $seconds));
        }
        $this->getLogger()->notice("Script mem usage: " . Alaress_Byte_Format::bytesToString(memory_get_peak_usage(true)));
    }
}
