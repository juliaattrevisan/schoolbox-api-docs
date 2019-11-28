<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async;


use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;

abstract class SignalEmitter implements EventEmitterInterface
{
    use EventEmitterTrait {
        on as onEvent;
        emit as emitEvent;
    }

	/**
	 * @param LoopInterface $loop
	 */
	public function __construct($loop)
    {
		$loop->addPeriodicTimer(0.1, function () {
			pcntl_signal_dispatch();
		});
	}


	/**
	 * Receive signals
	 *
	 * @param int $event
	 * @param callable $listener
	 */
	public function on($event, callable $listener)
    {
		pcntl_signal($event, [ $this, 'emit' ]);
        $this->onEvent($event, $listener);
	}


    /**
     * Workaround for `\Evenement\EventEmitterTrait::emit` attempting to use the
     * unpack array operator with associative arrays.
     *
     * @param int   $event
     * @param array $arguments
     */
	public function emit($event, array $arguments = [])
    {
        $this->emitEvent($event, array_values($arguments));
    }
}
