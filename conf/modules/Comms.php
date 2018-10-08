<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Schoolbox\Comms\News;


class Comms extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'comms';

        $this->loadModule($kernel, News\AudienceBrowser\Module::class, "/news/audiencebrowser");
        $this->loadModule($kernel, News\Moderation\Module::class, "/news/moderation");
        $this->loadModule($kernel, News\SaveForLater\Module::class, "/news/saved");
        $this->loadModule($kernel, News\Component\Dashboard\Module::class, "/news/component/dashboard");
        $this->loadModule($kernel, News\Component\Homepage\Module::class, "/news/component/homepage");
        $this->loadModule($kernel, News\Feed\Module::class, "/news/feed");
        $this->loadModule($kernel, News\Statistics\Module::class, "/news/statistics");
        $this->loadModule($kernel, News\Module::class, "/news");
    }
}
