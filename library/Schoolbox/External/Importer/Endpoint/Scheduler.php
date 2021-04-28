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
    public const SB = 'sb';
    public const HOURLY = 'hourly';
    public const TWICE_DAILY = 'twiceDaily';
    public const DAILY = 'daily';


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
     * Scheduler constructor.
     *
     * @param IDatabase       $db
     * @param string          $frequency
     * @param string[]        $customHours
     * @param IFactory        $factory
     * @param string          $type
     * @param string|null     $file
     * @param LoggerInterface $logger
     */
    public function __construct(
        IDatabase $db,
        string $frequency,
        array $customHours,
        IFactory $factory,
        string $type,
        ?string $file,
        LoggerInterface $logger
    ) {
        $this->db = $db;
        $this->frequency = $frequency;
        $this->customHours = $customHours;
        $this->factory = $factory;
        $this->type = $type;
        $this->file = $file;
        $this->logger = $logger;
    }


    /**
     * Execute the importer at $now
     *
     * @param int $now
     */
    public function run(int $now): void
    {
        if ((new ValidScheduleExecution())->isSatisfiedBy($this->frequency, $this->customHours, $now)) {
            $composites = $this->factory->produce($this->file, $this->type);

            /* No composites produced; exit
             * Either you chose an invalid $type or you don't need a $type at all (csv imports)
             */
            if (null === $composites) {
                exit;
            }

            try {
                $this->logger->debug(sprintf(
                    'Beginning transaction'
                ));

                $this->db->beginTransaction();

                foreach ($composites as $composite) {
                    $composite->import();
                }

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
            }
        }
    }
}
