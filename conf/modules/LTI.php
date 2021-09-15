<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\LTI\Component\Homepage\Module as LTIComponent;
use Schoolbox\LTI\Consumer\Module as LTIConsumer;
use Schoolbox\LTI\TestPlatform\Module as LtiTestPlatform;

class LTI extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $this->loadModule($kernel, LTIComponent::class, "/lti");
        $this->loadModule($kernel, LTIConsumer::class, "/lti-consumer");
        $this->loadModule($kernel, LtiTestPlatform::class, "/lti-test-platform");
    }
}
