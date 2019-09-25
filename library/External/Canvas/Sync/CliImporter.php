<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Aura\Di\Exception\ServiceNotFound;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External;
use Schoolbox\Resource\Folder;
use RuntimeException;
use Schoolbox\MapperFactory;
use UnexpectedValueException;
use function json_decode;

/**
 * Command line application for syncing canvas-data
 */
class CliImporter extends CliTool\App
{
    use CanvasDatabaseTrait;

    private const OPT_SHORT_HELP       = 'h';           // print help
    private const OPT_LONG_HELP        = 'help';        // print help
    private const OPT_LONG_SKIP_FETCH  = 'skip-fetch';  // Don't re-fetch files from Canvas API, use previously downloaded
    private const OPT_LONG_SKIP_IMPORT = 'skip-import'; // Don't re-import files into staging DB
    private const OPT_LONG_SKIP_SYNC   = 'skip-sync';   // Don't sync from staging DB to schoolbox DB

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
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ServiceNotFound
     */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        if ($this->perform()) {
            $this->getLogger()->info('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->getLogger()->info('Running in dry-run mode: use -p to write changes to database');
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
            $this->getLogger()->info("File download skipped");
            return;
        }
        $dumpFile = $tempDir . '/dump.json';

        // Fetch latest dump
        $this->getLogger()->info('Getting latest data urls.');
        try {
            $dumpResponse = $connector->fetchLatestDump();
        } catch (External\Exception $e) {
            $this->getLogger()->warning('Could not get dump file.' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $dump = json_decode($dumpResponse, true);
        $this->getLogger()->info("Getting schema version {$dump[$connector::SCHEMA_VERSION]}.");

        try {
            $schemaResponse = $connector->fetchSchema($dump[$connector::SCHEMA_VERSION]);
        } catch (External\Exception $e) {
            $this->getLogger()->warning('Could not fetch schema.' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        if ($this->perform()) {
            $this->storeTempFile($dumpResponse, $dumpFile);
            $this->storeTempFile($schemaResponse, $schemaFile);
        }

        // Download data files
        $this->getLogger()->info('Downloading latest data.');
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
            $this->getLogger()->info("File import to staging database skipped");
            return;
        }

        // Create schema in the staging database
        $dataRestorer = new DataRestorer($canvasDb, $this->getLogger(), $this->getCanvasDbName(), $this->perform());
        $this->getLogger()->info("Importing schema from json file: $schemaFile.");
        $schemaContainer = json_decode(file_get_contents($schemaFile), true);
        $schema          = $schemaContainer['schema'];
        $dataRestorer->restoreSchema($schema);

        // Store downloaded data to tables in the staging database
        $this->getLogger()->info('Restoring data to tables.');
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

            exec("gunzip " . escapeshellarg("$tempDir/$tableGzFile"));
            $dataRestorer->restoreTableData($tableName, $tableFile);
            exec("gzip " . escapeshellarg($tableFile));
        }
    }


    /**
     * Syncs data from canvas staging db to schoolbox db
     *
     * @param IConnector $canvasDb
     * @throws Database\Exception
     * @throws InvalidArgumentException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     * @throws PDOException
     */
    private function syncStagingDataWithSchoolbox(IConnector $canvasDb): void
    {
        if ($this->getOpt(self::OPT_LONG_SKIP_SYNC)) {
            $this->getLogger()->info("Sync from staging to schoolbox database skipped");
            return;
        }

        /** @var MapperFactory $mapperFactory */
        $mapperFactory = $this->getDi()->get('mapperFactory');
        /** @var Folder\Mapper $folderMapper */
        $folderMapper = $mapperFactory->get(Folder\Mapper::class);
        /** @var ConfigMapper $configMapper */
        $configMapper = $mapperFactory->get(ConfigMapper::class);

        /** @var IConnector $externalDb */
        $externalDb = $this->getDi()->get('databaseExternal');
        $dataSync   = new DataSync(
            $canvasDb,
            $this->getDatabaseInternal(),
            $externalDb,
            $folderMapper,
            $configMapper,
            $this->perform(),
            $this->getLogger()
        );
        $dataSync->synchronise();
    }

    /**
     * Creates a temp directory to store files and return its name
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
     * @param array $tableList
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
            $tableFile = $tableData['files'][0];
            $tableDataFile = fopen($this->perform() ? "$tempDir/$tableName.gz" : '/dev/null', 'wb');
            try {
                $this->getLogger()->debug("Downloading $tableName to $tempDir/$tableName.gz.");
                $client->get($tableFile['url'], ['save_to' => $tableDataFile]);
            } catch (GuzzleException $e) {
                $this->getLogger()->warning("Failed to download $tempDir/$tableName.gz: {$e->getMessage()}");
            }
        }
    }

    /**
     * Stores response to a file
     * @param string $response
     * @param string $path
     */
    public function storeTempFile(string $response, string $path): void
    {
        $fp = fopen($path, 'wb');
        fwrite($fp, $response);
        fclose($fp);
    }

    /**
     * Prints a help message, always to STDOUT, regardless of Kernel's output file option
     */
    private function printHelp(): void
    {
        $file = __FILE__;

        $options = [
            [self::OPT_SHORT_HELP, self::OPT_LONG_HELP, 'Print this help and exit'],
            [CliTool\Kernel::OPT_SHORT_PERFORM, CliTool\Kernel::OPT_LONG_PERFORM, 'If not set, no changes to local files, staging db, or schoolbox db will be persisted.'],
            [null, self::OPT_LONG_SKIP_FETCH, "If set, use most recently downloaded files, rather that fetching from API"],
            [null, self::OPT_LONG_SKIP_IMPORT, "If set, use most recent state of staging db, don't re-import files"],
            [null, self::OPT_LONG_SKIP_SYNC, "If set, don't sync staging data with schoolbox data"],
        ];

        $green = self::COLOUR_GREEN;
        $white = self::COLOUR_WHITE;
        $yellow = self::COLOUR_YELLOW;

        $help = "$white

WHAT THIS DOES:
    Downloads schema and data using Canvas API and populates the local canvas staging database named canvas_data_[INSTANCE_ID].
    This script expects the database canvas_data_[INSTANCE_ID] already be created.
    To create the database, use tools/canvas/create_database.php.

SYNOPSIS:
    $green{$file} {$yellow}[OPTION]...$white

OPTIONS:";
        foreach($options as $option){
            $short = $option[0] ? "-{$option[0]}" : '';
            $long  = $option[1] ? "--{$option[1]}" : '';
            $shortLong = implode(', ', array_filter([rtrim($short, ':') , rtrim($long, ':')]));
            $help .= "
    $yellow{$shortLong}$white
        $option[2]

";
        }
        \fwrite(STDOUT, $help);
    }
}
