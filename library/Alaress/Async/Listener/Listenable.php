<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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