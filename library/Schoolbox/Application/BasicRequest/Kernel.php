<?php
/**
 * This kernel is a stripped down version of the full web request kernel.  It is designed for use when there is
 * no user interface to display.  For example streaming files, images and videos.
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Application\BasicRequest;

class Kernel extends \Alaress\Application\Kernel
{
    /**
    * Initialise
    */
    public function __construct()
    {
        $this->boot = new Bootstrap($this);
        $this->boot();
    }


    /**
    * Execute the main script
    */
    public function run()
    {
    }
}
