<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Lc;

use Config\ModuleLoader;
use Custom\Lc\Survey\Component\Eportfolio\Module as EportfolioComponentModule;
use Custom\Lc\Survey\Module as SurveyModule;
use Psr\Container\ContainerInterface;

class Survey extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'survey';

        $this->loadModule($di, SurveyModule::class, "/{$namespace}");
        $this->loadModule($di, EportfolioComponentModule::class, "/{$namespace}");
    }
}
