<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TXRX;
use Alaress\WorkerCrew\Worker\AbstractProcess;
use Alaress\WorkerCrew\Worker\PipeWriter;
use BadMethodCallException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use RuntimeException;

class Worker extends AbstractProcess
{

    /** @var TXRX */
    private $txrx;

    /**
     * @param TXRX                      $txrx
     * @param LoopInterface             $loop
     * @param LoggerInterface           $log
     * @param PipeWriter                $writer
     * @param DispatchStrategyInterface $strat
     */
    public function __construct(TXRX $txrx, LoopInterface $loop, LoggerInterface $log, PipeWriter $writer, DispatchStrategyInterface $strat)
    {
        parent::__construct($loop, $log, $writer, $strat);
        $this->txrx = $txrx;
    }


    /**
     * @param string $command
     * @param array  $args
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws BadMethodCallException
     * @return mixed
     */
    protected function process($command, $args)
    {
        if ($command !== DispatchStrategy::TASK_ROUTE) {
            throw new InvalidArgumentException('Worker can only route');
        }

        $this->log->debug(sprintf('Iterating %d frame%s provided', count($args), count($args) === 1 ? '' : 's'));
        try {
            $count = $this->txrx->receive($args);
        } catch (InvalidArgumentException $e) {
            // Probably couldn't find the user in the audience:
            // raise a notice, but don't error out as there's naught that can be
            // done to fix this.
            $this->log->notice($e->getMessage(), ['exception' => $e]);
            return 0;
        }
        $this->log->info("Rerouted $count message" . ($count === 1 ? '' : 's'));
        return $count;
    }

}
