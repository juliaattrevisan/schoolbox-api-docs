<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Comms\News;

class Comms extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, News\AudienceBrowser\Module::class, "/news/audiencebrowser");
        $this->loadModule($di, News\Moderation\Module::class, "/news/moderation");
        $this->loadModule($di, News\SaveForLater\Module::class, "/news/saved");
        $this->loadModule($di, News\Component\Dashboard\Module::class, "/news/component/dashboard");
        $this->loadModule($di, News\Component\Homepage\Module::class, "/news/component/homepage");
        $this->loadModule($di, News\Feed\Module::class, "/news/feed");
        $this->loadModule($di, News\Statistics\Module::class, "/news/statistics");
        $this->loadModule($di, News\Module::class, "/news");

        // Discussion threads and comments for comms articles
        $this->loadModule($di, News\Discussion\Thread\Module::class, "/discussion/comms/{contextId}/threads");
        $this->loadModule($di, News\Discussion\Comment\Module::class, "/discussion/comms/{contextId}/comments");
    }
}
