<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;

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
    public const HOURLY = 'hourly';
    public const TWICE_DAILY = 'twiceDaily';
    public const DAILY = 'daily';


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
     * @param string          $frequency
     * @param Factory         $factory
     * @param string          $type
     * @param LoggerInterface $logger
     */
    public function __construct(string $frequency, Factory $factory, string $type, LoggerInterface $logger)
    {
        $this->frequency = $frequency;
        $this->factory = $factory;
        $this->type = $type;
        $this->logger = $logger;
    }


    /**
     * Execute the importer at $now
     *
     * @return void
     */
    public function run(int $now): void
    {
        if ((new ValidScheduleExecution())->isSatisfiedBy($this->frequency, $now)) {
            $composites = $this->factory->produce(null, $this->type);

            /* No composites produced; exit
             * Either you chose an invalid $type or you don't need a $type at all (csv imports)
             */
            if (null === $composites) {
                exit;
            }

            try {
                foreach ($composites as $composite) {
                    $composite->import();
                }
            } catch (\RuntimeException $e) {
                $this->logger->critical("Cannot access api endpoint: {$e->getMessage()}");
            } catch (InvalidUriPartException $e) {
                $this->logger->critical("Cannot access api endpoint, bad url: {$e->getMessage()}");
            } catch (InvalidArgumentException $e) {
                $this->logger->critical("Cannot access api endpoint, bad url: {$e->getMessage()}");
            }
        }
    }
}
