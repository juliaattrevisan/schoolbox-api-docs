<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\LTI\Component\Homepage\Module as LTIComponent;
use Schoolbox\LTI\Consumer\Module as LTIConsumer;

class LTI extends ModuleLoader implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $this->loadModule($kernel, LTIComponent::class,     "/lti");
        $this->loadModule($kernel, LTIConsumer::class,      "/lti-consumer");
    }
}
