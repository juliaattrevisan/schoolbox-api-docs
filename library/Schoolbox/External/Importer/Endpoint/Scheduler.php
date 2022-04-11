<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Alaress\IDatabase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Class Scheduler
 *
 * @package Schoolbox\External\Importer\Endpoint
 */
class Scheduler
{
    /**
     * The options you can choose
     */
    public const SB          = 'sb';
    public const HOURLY      = 'hourly';
    public const TWICE_DAILY = 'twiceDaily';
    public const DAILY       = 'daily';
    public const NONE        = 'none';


    /**
     * This script name appears in the import log
     *
     * sisImporter is when you run an on-demand suds import via sisImporter.php
     *
     * scheduler.php is a two-part system, the first imports regular sis data
     * such as profiles and timetables - this manifests as "School Data Import (SIS)"
     *
     * The other part is TASS Calendar, which manifests as "School Data Import (Calendar)"
     */
    public const SISIMPORTER_SCRIPT_NAME = 'sisImporter';
    public const CALENDARIMPORTER_SCRIPT_NAME = 'calendarImporter';
    public const SCHEDULER_SIS_SCRIPT_NAME = 'School Data Import (SIS)';
    public const SCHEDULER_CALENDAR_SCRIPT_NAME = 'School Data Import (Calendar)';

    /**
     * max execution time before we consider the import process timed out.
     */
    public const DEFAULT_TIMEOUT = 10800;

    /**
     * lock identifier used to prevent more than one import process from running at a time
     */
    public const LOCK_NAME = 'schooldata_import';

    /**
     * @var IDatabase
     */
    private $db;

    /**
     * One of [hourly, twiceDaily, daily]
     *
     * @var string
     */
    private $frequency;

    /**
     * Custom hours to run tool at, eg ['00','03','15','23']
     *
     * @var string[]
     */
    private array $customHours;

    /**
     * @var IFactory
     */
    private $factory;

    /**
     * Optional path to a file containing cached nodes that can be read instead of
     * realtime endpoint access (nicer for development/debugging)
     *
     * @var string|null
     */
    private $file;

    /**'
     * @var string
     */
    private $type;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @var \Schoolbox\External\Connector\Log\Mapper
     */
    private \Schoolbox\External\Connector\Log\Mapper $importLogMapper;


    /**
     * Scheduler constructor.
     *
     * @param IDatabase                                $db
     * @param string                                   $frequency
     * @param string[]                                 $customHours
     * @param IFactory                                 $factory
     * @param string                                   $type
     * @param string|null                              $file
     * @param LoggerInterface                          $logger
     * @param \Schoolbox\External\Connector\Log\Mapper $importLogMapper
     */
    public function __construct(
        IDatabase $db,
        string $frequency,
        array $customHours,
        IFactory $factory,
        string $type,
        ?string $file,
        LoggerInterface $logger,
        \Schoolbox\External\Connector\Log\Mapper $importLogMapper
    ) {
        $this->db = $db;
        $this->frequency = $frequency;
        $this->customHours = $customHours;
        $this->factory = $factory;
        $this->type = $type;
        $this->file = $file;
        $this->logger = $logger;
        $this->importLogMapper = $importLogMapper;
    }


    /**
     * Execute the importer at $now
     *
     * @param int    $now
     * @param string $runId
     * @param string $scriptName
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function run(int $now, string $runId, string $scriptName): void
    {
        if ((new ValidScheduleExecution())->isSatisfiedBy($this->frequency, $this->customHours, $now)) {
            try {
                $composites = $this->factory->produce($this->file, $this->type);
            } catch (\Exception $e) {
                /* Specifically log when you couldn't produce the recipe, then leave
                 * Anything could have caused that, so you might as well log it
                 * then throw it back out
                 */
                $importLog = $this->importLogMapper->start($runId, $scriptName);
                $this->importLogMapper->error($importLog, $e->getMessage());

                throw $e;
            }

            /* No composites produced; exit
             *  - You chose an invalid $type
             *  - You don't need a $type at all (csv imports)
             *  - You tried accessing an optional module like TASS Calendar, but it wasn't in the DSN
             *  - The factory produced an empty set for some reason
             */
            if (null === $composites || !$composites->count()) {
                exit;
            }

            /* Always log the start of the run
             */
            $importLog = $this->importLogMapper->start($runId, $scriptName);

            try {
                $this->logger->debug(sprintf(
                    'Beginning transaction'
                ));

                $this->db->beginTransaction();

                foreach ($composites as $composite) {
                    $composite->import();
                }

                /* Augment the Factory's recipe with the finisher
                 *
                 * This way you don't have to remember doing it in every factory;
                 * you literally cannot forget to do it. It'll only log the finish
                 * if you didn't crash somewhere in the process
                 */
                $this->importLogMapper->finish($importLog);

                /* Trim away ancient logs
                 */
                $this->importLogMapper->prune();

                $this->logger->debug(sprintf(
                    'Committing transaction'
                ));

                $this->db->commit();
            } catch (RuntimeException $e) {
                $this->logger->critical("Cannot access api endpoint: {$e->getMessage()}");

                $this->logger->debug(sprintf(
                    'Rollback transaction'
                ));

                $this->db->rollBack();

                /* Something happened with the api; log it
                 */
                $this->importLogMapper->error($importLog, $e->getMessage());
            } catch (\Throwable | \Exception $e) {
                $this->logger->critical("Cannot access api endpoint: {$e->getMessage()}");

                $this->logger->debug(sprintf(
                    'Rollback transaction'
                ));

                $this->db->rollBack();

                /* Some kind of unexpected fatal; log it
                 */
                $this->importLogMapper->error($importLog, $e->getMessage());

                /* Also rethrow it, so you get an error in the face
                 */
                throw $e;
            }
        }
    }
}
