<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread;

/**
 * Discussion module
 */
class Discussion implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel): void
    {
        $commentModule = new Comment\Module($kernel->getDependencyInjector());
        $commentModule->init('/comments');

        $threadModule = new Thread\Module($kernel->getDependencyInjector());
        $threadModule->init('/threads');
    }
}
