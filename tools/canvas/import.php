#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

use External\Canvas\Sync\DataFetcher;
use External\Canvas\Sync\DataRestorer;
use External\Canvas\Sync\Importer;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Alaress\Database\Broker\Mysql;
use Alaress\Database\Connector;
use Alaress\Database;


require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Command line application for syncing canvas-data
 */
$kernel = new CliTool\Kernel(new class extends CliTool\App
{
    private $canvasDbName;
    private $dbHost;

    /**
     * Hands off execution over to the App
     *
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws Database\Predicate\InvalidColumnException
     */
    public function run(): void
    {
        $this->canvasDbName = 'canvas_data_' . INSTANCE_ID;
        $this->dbHost = '192.168.36.10';

        // Get DB
        try {
            $db = $this->buildCanvasDbConnection();
            $db->executeQuery("DROP DATABASE IF EXISTS `{$this->canvasDbName}`");
            $db->executeQuery("CREATE DATABASE `{$this->canvasDbName}`");
        } catch (\Alaress\Database\Exception $e) {
            $this->getLogger()->warning("Unable to connect to database {$this->canvasDbName}: " . $e->getMessage());
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        if (!$this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_KEY) ||
            !$this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_SECRET)) {
            $this->getLogger()->warning('API Key or secret is not set.');
            exit(CliKernelAbstract::EXIT_STATUS_KERNEL_ERROR);
        }

        $tempDir = $this->createTempDir();
        $fetcher = new DataFetcher(
            $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_KEY),
            $this->getConfig()->get(ConfigMapper::_CANVAS_DATA_API_SECRET),
            $tempDir,
            $this->getLogger()
        );

        // Fetch schema
        $this->getLogger()->debug('Getting schema');
        $schemaFile = $fetcher->fetchSchema();

        // Create schema in the staging database
        $dataRestorer = new DataRestorer($db, $this->getLogger(), $this->canvasDbName);
        $this->getLogger()->debug('Importing schema from json file:' . $schemaFile);
        $dataRestorer->restoreSchema($schemaFile);

        // Fetch data file URLs and download data files
        $this->getLogger()->debug('Getting latest data url');
        $dumpFile = $fetcher->fetchDataUrl();
        $this->getLogger()->debug('Downloading latest data');
        $fetcher->downloadDataFiles($dumpFile);

        // Store downloaded data to tables in the staging database
        $this->getLogger()->debug('Restoring data to tables');
        $dataRestorer->restoreTableData($tempDir);

        // Import data in the canvas staging database to schoolbox database
        $this->getLogger()->debug('Converting and importing canvas data to schoolbox database');
        /** @var \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy */
        $assessmentMapperProxy = $this->getDi()->get('assessmentDataSource');
        /** @var \External_ClassInterface $externalClass */
        $externalClass = $this->getDi()->get('externalClass');

        $importer   = new Importer(
            $externalClass,
            $this->getDatabaseInternal(),
            $this->buildCanvasDbConnection(),
            $assessmentMapperProxy,
            $this->getConfig(),
            $this->getLogger()
        );
        $importer->import();
    }

    /**
     * Create a temp directory to store files and return its name
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
        return $tempDir;
    }

    /**
     * Returns a connection to the Canvas staging database
     *
     * @return Database\Connector\IConnector
     */
    private function buildCanvasDbConnection(): Database\Connector\IConnector
    {
        $connector = new Connector\Base(new Mysql\Broker());

        $connectionOptions = [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];
        $connector->connect(
            $this->dbHost,
            $this->canvasDbName,
            SQL_user,
            SQL_passwd,
            $connectionOptions
        );

        return $connector;
    }

}, false);
$kernel->runApp();
