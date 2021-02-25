<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Alaress\Database\Connector\IConnector;
use DomainException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
use UnexpectedValueException;

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
     * @var IConnector
     */
    private $db;


    /**
     * One of [hourly, twiceDaily, daily]
     *
     * @var string
     */
    private $frequency;


    /**
     * @var Factory
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
     * @param IConnector      $db
     * @param string          $frequency
     * @param Factory         $factory
     * @param string          $type
     * @param string|null     $file
     * @param LoggerInterface $logger
     */
    public function __construct(IConnector $db, string $frequency, Factory $factory, string $type, ?string $file, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->frequency = $frequency;
        $this->factory = $factory;
        $this->type = $type;
        $this->file = $file;
        $this->logger = $logger;
    }


    /**
     * Execute the importer at $now
     *
     * @param int $now
     * @throws RuntimeException
     * @throws UnexpectedValueException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function run(int $now): void
    {
        if ((new ValidScheduleExecution())->isSatisfiedBy($this->frequency, $now)) {
            $composites = $this->factory->produce($this->file, $this->type);

            /* No composites produced; exit
             * Either you chose an invalid $type or you don't need a $type at all (csv imports)
             */
            if (null === $composites) {
                exit;
            }

            // For some reason, the mappers don't use `$this->db` as their DB
            // connection: this means that transactions on that connection are
            // ignored by mappers.
            // Just pluck a random mapper and transact over that.
            // TODO undo this once mappers and `$this->db` share a connection
            $randomMapper = UserMapper::getInstance();
            try {
                $this->logger->debug(sprintf(
                    'Beginning transaction'
                ));

                $randomMapper->getDatabase()->beginTransaction();

                foreach ($composites as $composite) {
                    $composite->import();
                }

                $this->logger->debug(sprintf(
                    'Committing transaction'
                ));

                $randomMapper->getDatabase()->commit();
            } catch (RuntimeException $e) {
                $this->logger->critical("Cannot access api endpoint: {$e->getMessage()}");

                $this->logger->debug(sprintf(
                    'Rollback transaction'
                ));

                $randomMapper->getDatabase()->rollBack();
            }
        }
    }
}
