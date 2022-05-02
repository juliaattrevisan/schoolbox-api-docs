<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Survey\Module as SurveyModule;

class Survey extends ModuleLoader implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'survey';

        $this->loadModule($di, SurveyModule::class, "/{$namespace}");
    }
}
