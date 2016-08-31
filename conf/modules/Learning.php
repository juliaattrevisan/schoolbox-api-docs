<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Learning\Module as LearningModule;
use Schoolbox\Learning\Assessment\Module as AssessmentModule;
use Schoolbox\Learning\Feedback\Module as FeedbackModule;
use \External_User;

class Learning implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'learning';
        $di = $kernel->getDependencyInjector();

        $learningModule = new LearningModule($kernel->getDependencyInjector());
        $learningModule
            ->setExternalDb($di->newInstance(External_User::class))
            ->init("/$namespace");

        $assessmentModule = new AssessmentModule($kernel->getDependencyInjector());
        $assessmentModule
            ->setExternalDb($di->newInstance(External_User::class))
            ->init("/$namespace");

        $feedbackModule = new FeedbackModule($kernel->getDependencyInjector());
        $feedbackModule
            ->setExternalDb($di->newInstance(External_User::class))
            ->init("/$namespace");
    }
}
