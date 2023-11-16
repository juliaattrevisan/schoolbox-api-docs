<?php

/**
 * @author      James Leckie <james.leckie@schoolbox.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Editor\OpenAI\Module;

class OpenAI implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'openai';

        $module = new Module($di);
        $module->registerModule("/{$namespace}");
    }
}
