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
use Schoolbox\LTI\Assessment\Component\Homepage\Module as LtiAssessmentComponent;
use Schoolbox\LTI\Component\Homepage\Module as LtiComponent;
use Schoolbox\LTI\Consumer\Module as LtiConsumer;
use Schoolbox\LTI\Prototype\Platform\Module as LtiPrototypePlatform;
use Schoolbox\LTI\Prototype\Tool\Module as LtiPrototypeTool;

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
        $this->loadModule($kernel, LtiComponent::class, "/lti");
        $this->loadModule($kernel, LtiConsumer::class, "/lti-consumer");
        $this->loadModule($kernel, LtiPrototypePlatform::class, "/lti-prototype/platform");
        $this->loadModule($kernel, LtiPrototypeTool::class, "/lti-prototype/tool");
        $this->loadModule($kernel, LtiAssessmentComponent::class, "/lti-assessment/component");
    }
}
