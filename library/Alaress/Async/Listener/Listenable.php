<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Listener;

interface Listenable
{

    /**
     * @param EventListener $listener
     * @return void
     */
    public function attachListener(EventListener $listener);


    /**
     * @param EventListener $listener
     * @return void
     */
    public function detachListener(EventListener $listener);
}
