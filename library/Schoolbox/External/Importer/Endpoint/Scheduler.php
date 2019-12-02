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
    private const HOURLY = 'hourly';
    private const TWICE_DAILY = 'twiceDaily';
    private const DAILY = 'daily';


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
     * Decide whether to execute the importer
     */
    public function run()
    {

        /* Prepare a default collection
         */
        $composites = new GenericCollection(IStrategy::class);

        if ($frequency == 'hourly') {

            /* If the frequency is hourly, just do it
             * We'll make the assumption that Support won't configure it
             * to run more frequently than hourly anyway
             */
            $composites = $this->factory->produce(null, $this->type);

        } elseif ($frequency == 'twiceDaily') {

            /* If the frequency is twiceDaily,
             * Check that the current hour is 4am or 4pm
             * If it is, just do it
             * Otherwise, noop
             */

        } elseif ($frequency == 'daily') {

            /* If the frequency is daily,
             * Checkt hat the current hour is 1am
             * If it is, just do it
             * Otherwise, noop
             */
        }

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
