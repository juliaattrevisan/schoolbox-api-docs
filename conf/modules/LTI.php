<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\LTI\Component\Homepage\Module as LtiComponent;
use Schoolbox\LTI\Consumer\Module as LtiConsumer;
use Schoolbox\LTI\Platform\Module as LtiPlatform;
use Schoolbox\LTI\Platform\Context\Module as LtiPlatformContext;
use Schoolbox\LTI\Platform\LineItem\Module as LtiPlatformLineItem;
use Schoolbox\LTI\Prototype\Platform\Module as LtiPrototypePlatform;
use Schoolbox\LTI\Prototype\Tool\Module as LtiPrototypeTool;

class LTI extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, LtiComponent::class, "/lti");
        $this->loadModule($di, LtiConsumer::class, "/lti-consumer");

        // LTI 1.3 platform
        $this->loadModule($di, LtiPlatform::class, "/lti-platform");
        $this->loadModule($di, LtiPlatformContext::class, "/lti-platform/context");
        $this->loadModule($di, LtiPlatformLineItem::class, "/lti-platform/line-item");

        // Prototype platform and tool
        $this->loadModule($di, LtiPrototypePlatform::class, "/lti-prototype/platform");
        $this->loadModule($di, LtiPrototypeTool::class, "/lti-prototype/tool");
    }
}
