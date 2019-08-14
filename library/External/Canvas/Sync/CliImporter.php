<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use External\Canvas\Sync\Connector as CanvasConnector;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Exception;
use RuntimeException;

/**
 * Command line application for syncing canvas-data
 */
class CliImporter extends CliTool\App
{
    use CanvasDatabaseTrait;

    private const OPT_SHORT_HELP       = 'h';                 // print help
    private const OPT_LONG_HELP        = 'help';              // print help

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
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        // Did user request help?
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->printHelp();
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        // Get DB
        try {
            $db = $this->buildCanvasDbConnection();
            if ($this->perform()) {
                $this->dropCanvasDbTables($db);
            }
        } catch (\Alaress\Database\Exception $e) {
            $this->getLogger()->warning(
                sprintf('Unable to connect to database %s: %s. Use tools/canvas/create_database.php to create database.',
                    $this->getCanvasDbName(),
                    $e->getMessage()
                ));
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        // Check api config
        if (!$this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_KEY) ||
            !$this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_SECRET)) {
            $this->getLogger()->warning('API Key or secret is not set.');
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $tempDir = $this->createTempDir();
        $dumpFile = $tempDir . '/dump.json';
        $schemaFile = $tempDir . '/dump.json';
        $connector = new CanvasConnector(
            $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_KEY),
            $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_SECRET)
        );

        // Fetch latest dump
        try {
            $this->getLogger()->debug('Getting latest data url');
            $dumpResponse = $connector->fetchLatestDump();
            $this->storeTempFile($dumpResponse, $dumpFile);
            $dump = json_decode($dumpResponse, true);
            $this->getLogger()->debug("Getting schema version {$dump[$connector::SCHEMA_VERSION]}");
            $schemaResponse = $connector->fetchSchema($dump[$connector::SCHEMA_VERSION]);
            $this->storeTempFile($schemaResponse, $schemaFile);
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not get dump file.' . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        // Download data files
        $this->getLogger()->debug('Downloading latest data');
        $this->downloadDataFiles($dump['artifactsByTable'], $tempDir);

        if ($this->perform()) {
            // Create schema in the staging database
            $dataRestorer = new DataRestorer($db, $this->getLogger(), $this->getCanvasDbName());
            $this->getLogger()->debug('Importing schema from json file:' . $schemaFile);
            $schemaContainer = json_decode(file_get_contents($schemaFile), true);
            $schema = $schemaContainer['schema'];
            $dataRestorer->restoreSchema($schema);

            // Store downloaded data to tables in the staging database
            $this->getLogger()->debug('Restoring data to tables');
            if (!$dh = @opendir($tempDir)) {
                $this->getLogger()->warning("Failed to open directory $tempDir");
                exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
            }
            while (false !== ($tableGzFile = readdir($dh))) {
                $posExt = strpos($tableGzFile, '.gz');
                if (!$posExt) {
                    continue;
                }
                $tableName = substr($tableGzFile, 0, $posExt);
                $tableFile = "$tempDir/$tableName";
                $this->getLogger()->debug("Loading  $tableFile");

                exec("gunzip $tempDir/$tableGzFile");
                $dataRestorer->restoreTableData($tableName, $tableFile);
                exec("gzip $tableFile");
            }
        }
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
     * @param $tableList
     * @param $tempDir
     */
    public function downloadDataFiles(array $tableList, string $tempDir): void
    {
        $client = new Client();
        foreach ($tableList as $tableName => $tableData) {
            if (!count($tableData['files'])) {
                continue;
            }
            $tableFile = $tableData['files'][0];
            $tableDataFile = fopen("$tempDir/$tableName.gz", 'w');
            try {
                $response = $client->get($tableFile['url'], ['save_to' => $tableDataFile]);
                $this->getLogger()->debug("downloading $tableName to $tableDataFile");
            } catch (GuzzleException $e) {
                $this->getLogger()->warning("Failed to download $tableDataFile: " . $e->getMessage());
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

OPTIONS:
    $yellow-h, --help$white
        Print this help and exit
        
    $yellow-p, --perform$white
        If set, updates canvas staging DB - otherwise, only downloads data
";
        \fwrite(STDOUT, $help);
    }
}
