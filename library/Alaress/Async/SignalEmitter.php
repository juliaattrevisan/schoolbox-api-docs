<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async;


use Evenement\EventEmitterInterface;
use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;

abstract class SignalEmitter implements EventEmitterInterface
{
    use EventEmitterTrait {
        on as onEvent;
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

}
