<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Importer\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\GenericCollection;

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
     * When the scheduler was asked to run
     *
     * @var int
     */
    private $now;


    /**
     * Scheduler constructor.
     *
     * @param string $schedule
     */
    public function __construct(string $frequency, Factory $factory, string $type, int $now)
    {
        $this->frequency = $frequency;
        $this->factory = $factory;
        $this->type = $type;
        $this->now = $now;
    }


    /**
     * Execute the importer
     *
     * @return void
     */
    public function run(): void
    {
       if ($this->isValidScheduleExecution()) {
            $composites = $this->factory->produce(null, $this->type);

            try {
                foreach ($composites as $composite) {
                    $composite->import();
                }
            } catch (\RuntimeException $e) {
                $this->getLogger()->critical("Cannot access api endpoint: {$e->getMessage()}");
            } catch (InvalidUriPartException $e) {
                $this->getLogger()->critical("Cannot access api endpoint, bad url: {$e->getMessage()}");
            } catch (InvalidArgumentException $e) {
                $this->getLogger()->critical("Cannot access api endpoint, bad url: {$e->getMessage()}");
            }
        }
    }


    /**
     * Decide whether to execute the importer
     *
     * @return bool
     */
    private function isValidScheduleExecution(): bool
    {
        /* If the frequency is hourly, just do it
         * We'll make the assumption that Support won't configure it
         * to run more frequently than hourly anyway
         */
        if ($this->frequency == self::HOURLY) {
            return true;
        }

        /* If the frequency is twiceDaily,
         * Check that the current hour is 4am or 4pm
         * If it is, just do it
         * Otherwise, noop
         */
        if (($this->frequency == self::TWICE_DAILY) && (4 === date('g', $this->now))) {
            return true;
        }

        /* If the frequency is daily,
         * Check that the current hour is 1am
         * If it is, just do it
         * Otherwise, noop
         */
        if (($this->frequency == self::DAILY) && (1 == date('G', $this->now))) {
            return true;
        }

        /* It's not time to run yet
         */
        return false;
    }
}
