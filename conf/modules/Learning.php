<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\Learning\Module as LearningModule;
use Schoolbox\Learning\Markbook\Module as MarkbookModule;
use Schoolbox\Learning\Assessment\Module as AssessmentModule;
use Schoolbox\Learning\Feedback\Module as FeedbackModule;
use Schoolbox\Learning\Quiz\Module as QuizModule;
use Schoolbox\Learning\Response\Module as ResponseModule;
use Schoolbox\Learning\Upcoming\Module as UpcomingModule;
use Schoolbox\Learning\Assessment\Exporter\Module as AssessmentExporterModule;

class Learning extends ModuleLoader implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace  = 'learning';

        $this->loadModule($kernel, LearningModule::class,           "/{$namespace}");
        $this->loadModule($kernel, AssessmentModule::class,         "/{$namespace}");
        $this->loadModule($kernel, FeedbackModule::class,           "/{$namespace}");
        $this->loadModule($kernel, ResponseModule::class,           "/{$namespace}");
        $this->loadModule($kernel, QuizModule::class,               "/{$namespace}");
        $this->loadModule($kernel, MarkbookModule::class,           "/{$namespace}/markbook");
        $this->loadModule($kernel, UpcomingModule::class,           "/{$namespace}/due");
        $this->loadModule($kernel, AssessmentExporterModule::class, "/{$namespace}/export");
    }
}
