<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress_Byte_Format;
use Aura\Di\Exception\ServiceNotFound;
use DomainException;
use External_ClassInterface;
use External_UserInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Learning\Quiz;
use Schoolbox\Mapper;
use Schoolbox\Resource\Folder;
use RuntimeException;
use Schoolbox\MapperFactory;
use Schoolbox\UserMapper;
use UnderflowException;
use UnexpectedValueException;
use function fclose;
use function file_exists;
use function floor;
use function fopen;
use function fwrite;
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

    use CanvasDatabaseTrait;

    private const OPT_SHORT_HELP = 'h';           // print help
    private const OPT_LONG_HELP = 'help';        // print help
    private const OPT_LONG_SKIP_FETCH = 'skip-fetch';  // Don't re-fetch files from Canvas API, use previously downloaded
    private const OPT_LONG_SKIP_IMPORT = 'skip-import'; // Don't re-import files into staging DB
    private const OPT_LONG_SKIP_SYNC = 'skip-sync';   // Don't sync from staging DB to schoolbox DB


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
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws RuntimeException
     * @throws ServiceNotFound
     * @throws UnderflowException
     * @throws UnexpectedValueException
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

        // Ensure we can connect to canvas API + staging DB prior to executing any actions.
        $canvasApi = $this->buildCanvasApiConnectionOrExit();
        $canvasDb  = $this->buildCanvasDbConnectionOrExit();

        $tempDir    = $this->createTempDir();
        $schemaFile = $tempDir . '/schema.json';

        // Execute tool actions.
        $this->fetchCanvasDataFilesFromApi($canvasApi, $tempDir, $schemaFile);
        $this->loadCanvasDataFilesToStagingDatabase($canvasDb, $tempDir, $schemaFile);
        $this->syncStagingDataWithSchoolbox($canvasDb);
        $this->logStats();
    }


    /**
     * Builds and returns a connection to Canvas Data API. Exits if unable to do so.
     *
     * @return Connector
     */
    private function buildCanvasApiConnectionOrExit(): Connector
    {
        // Check api config
        if (!$this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_KEY) ||
            !$this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_SECRET)) {
            $this->getLogger()->warning('API Key or secret is not set.');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        return new Connector(
            $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_KEY),
            $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_SECRET)
        );
    }


    /**
     * Builds and returns a Canvas DB connection if possible, otherwise exits
     *
     * @return IConnector
     */
    private function buildCanvasDbConnectionOrExit(): IConnector
    {
        try {
            return $this->buildCanvasDbConnection();
        } catch (Database\Exception $e) {
            $this->getLogger()->error(
                sprintf(
                    'Unable to connect to database %s: %s. Use tools/canvas/create_database.php to create database.',
                    $this->getCanvasDbName(),
                    $e->getMessage()
                )
            );
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
    }


    /**
     * Downloads data from CanvasData API and saves to local temp files
     *
     * @param Connector $connector
     * @param string    $tempDir
     * @param string    $schemaFile
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    private function fetchCanvasDataFilesFromApi(Connector $connector, string $tempDir, string $schemaFile): void
    {
        if ($this->getOpt(self::OPT_LONG_SKIP_FETCH)) {
            $this->getLogger()->notice("File download skipped");
            return;
        }
        $dumpFile = $tempDir . '/dump.json';

        // Fetch latest dump
        $this->getLogger()->notice('Getting latest data urls.');
        try {
            $dumpResponse = $connector->fetchLatestDump();
        } catch (External\Exception $e) {
            $this->getLogger()->warning('Could not get dump file.' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $dump = json_decode($dumpResponse, true);
        $this->getLogger()->notice("Getting schema version {$dump[$connector::SCHEMA_VERSION]}.");

        try {
            $schemaResponse = $connector->fetchSchema($dump[$connector::SCHEMA_VERSION]);
        } catch (External\Exception $e) {
            $this->getLogger()->warning('Could not fetch schema.' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $this->storeTempFile($dumpResponse, $dumpFile);
        $this->storeTempFile($schemaResponse, $schemaFile);

        // Download data files
        $this->getLogger()->notice('Downloading latest data.');
        $this->downloadDataFiles($dump['artifactsByTable'], $tempDir);
    }


    /**
     * Processes local files to build schema and data in local canvas staging database
     *
     * @param IConnector $canvasDb
     * @param string     $tempDir
     * @param string     $schemaFile
     * @throws Database\Exception
     * @throws UnexpectedValueException
     */
    private function loadCanvasDataFilesToStagingDatabase(IConnector $canvasDb, string $tempDir, string $schemaFile): void
    {
        if ($this->getOpt(self::OPT_LONG_SKIP_IMPORT)) {
            $this->getLogger()->notice("File import to staging database skipped");
            return;
        }

        // Create schema in the staging database
        $dataRestorer = new DataRestorer($canvasDb, $this->getLogger(), $this->getCanvasDbName(), $this->perform());
        $this->getLogger()->notice("Importing schema from json file: $schemaFile.");
        $schemaContainer = json_decode(file_get_contents($schemaFile), true);
        $schema          = $schemaContainer['schema'];
        $dataRestorer->restoreSchema($schema);

        // Store downloaded data to tables in the staging database
        $this->getLogger()->notice('Restoring data to tables.');
        $tableGzFiles = scandir($tempDir);
        if (!$tableGzFiles || !count($tableGzFiles)) {
            $this->getLogger()->warning("Failed to open directory $tempDir.");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        foreach ($tableGzFiles as $tableGzFile) {
            $posExt = strpos($tableGzFile, '.gz');
            if (!$posExt) {
                continue;
            }
            $tableName = substr($tableGzFile, 0, $posExt);
            $tableFile = "$tempDir/$tableName";
            $this->getLogger()->debug("Loading $tableFile.");

            exec("gunzip -f -k " . escapeshellarg("$tempDir/$tableGzFile")); // force overwrite of existing files, keep original
            $dataRestorer->restoreTableData($tableName, $tableFile);
            unlink($tableFile);
        }
        $dataRestorer->addExtraIndexes();
    }


    /**
     * Syncs data from canvas staging db to schoolbox db
     *
     * @param IConnector $canvasDb
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     * @throws Mapper\Exception
     * @throws UnderflowException
     * @throws DomainException
     */
    private function syncStagingDataWithSchoolbox(IConnector $canvasDb): void
    {
        if ($this->getOpt(self::OPT_LONG_SKIP_SYNC)) {
            $this->getLogger()->notice("Sync from staging to schoolbox database skipped");
            return;
        }

        /** @var MapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        /** @var External_ClassInterface $externalClass */
        $externalClass = $this->getDi()->get('externalClass');
        /** @var External_UserInterface $externalUser */
        $externalUser  = $this->getDi()->get('externalUser');
        /** @var MarkTypeMapper */
        $markTypeMapper = $mapperFactory->get(MarkTypeMapper::class);
        /** @var Quiz\Mapper */
        $quizMapper = $mapperFactory->get(Quiz\Mapper::class);
        /** @var SubmissionBoxMapper */
        $submissionBoxMapper = $mapperFactory->get(SubmissionBoxMapper::class);
        /** @var Assessment\Mapper */
        $assessmentMapper = $mapperFactory->get(Assessment\Mapper::class);
        $planMapper = $mapperFactory->get(Participant\Plan\Mapper::class);
        $participantMapper = $mapperFactory->get(Participant\Mapper::class);
        /** @var Folder\Mapper */
        $folderMapper = $mapperFactory->get(Folder\Mapper::class);
        /** @var UserMapper */
        $userMapper = $mapperFactory->get(UserMapper::class);
        /** @var CourseImpl\Mapper */
        $courseImplMapper = $mapperFactory->get(CourseImpl\Mapper::class);
        $courseSpecMapper = $mapperFactory->get(CourseSpec\Mapper::class);
        /** @var UnitImpl\Mapper */
        $unitImplMapper = $mapperFactory->get(UnitImpl\Mapper::class);
        $unitSpecMapper = $mapperFactory->get(UnitSpec\Mapper::class);
        /** @var IConfigLoader */
        $config = $this->getDi()->get('config');

        $dataSync = new DataSync(
            $canvasDb,
            $this->getDatabaseInternal(),
            $externalClass,
            $externalUser,
            $markTypeMapper,
            $quizMapper,
            $submissionBoxMapper,
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
     * @return string
     * @throws RuntimeException If unable to create the temp directory
     */
    private function createTempDir(): string
    {
        $tempDir = $this->getConfig()->get('zip_dir') . '/canvas-data';
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
     * @param array  $tableList
     * @param string $tempDir
     * @throws InvalidArgumentException If we attempt to create a Guzzle client with invalid handler/s
     */
    private function downloadDataFiles(array $tableList, string $tempDir): void
    {
        $client = new Client();
        foreach ($tableList as $tableName => $tableData) {
            if (!count($tableData['files'])) {
                continue;
            }
            $tableFile     = $tableData['files'][0];
            $fileName      = "$tempDir/$tableName.gz";
            $tableDataFile = fopen(($this->perform() || !file_exists($fileName)) ? $fileName  : '/dev/null', 'wb');
            try {
                $this->getLogger()->debug("Downloading $tableName to $fileName");
                $client->get($tableFile['url'], ['save_to' => $tableDataFile]);
            } catch (GuzzleException $e) {
                $this->getLogger()->warning("Failed to download $fileName: {$e->getMessage()}");
            }
        }
    }


    /**
     * Stores response to a file
     *
     * Will only overwrite a previously saved file if tool running in 'perform' mode
     *
     * @param string $response
     * @param string $path
     */
    public function storeTempFile(string $response, string $path): void
    {
        $this->getLogger()->debug("Saving temp file to path '$path'");
        if ($this->perform() || !file_exists($path)) {
            $fp = fopen($path, 'wb');
            fwrite($fp, $response);
            fclose($fp);
        }
    }


    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
    {
        $options = [
            [self::OPT_SHORT_HELP, self::OPT_LONG_HELP, 'Print this help and exit'],
            [CliTool\Kernel::OPT_SHORT_PERFORM, CliTool\Kernel::OPT_LONG_PERFORM, 'Persist changes to local files, staging DB + Schoolbox DB'],
            [null, self::OPT_LONG_SKIP_FETCH, "If set, use most recently downloaded files, rather that fetching from API"],
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
