<?php
/**
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Application\BasicRequest;

use Schoolbox\Application\WebKernel;

/**
 * This kernel is a stripped down version of the full web request kernel.  It is designed for use when there is
 * no user interface to display.  For example streaming files, images and videos.
 */
class Kernel extends WebKernel
{

    /**
     * Initialise
     */
    public function __construct()
    {
        $this->boot(new Bootstrap($this));
    }


    /**
     * Execute the main script
     */
    public function run()
    {
    }
}
