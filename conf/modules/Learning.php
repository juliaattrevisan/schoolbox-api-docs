<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Config\Modules;

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

        $this->loadModule(LearningModule::class, "/{$namespace}", $kernel);
        $this->loadModule(AssessmentModule::class, "/{$namespace}", $kernel);
        $this->loadModule(FeedbackModule::class, "/{$namespace}", $kernel);
        $this->loadModule(ResponseModule::class, "/{$namespace}", $kernel);
        $this->loadModule(QuizModule::class, "/{$namespace}", $kernel);
        $this->loadModule(MarkbookModule::class, "/{$namespace}/markbook", $kernel);
        $this->loadModule(UpcomingModule::class, "/{$namespace}/due", $kernel);
        $this->loadModule(AssessmentExporterModule::class, "/{$namespace}/export", $kernel);
    }
}
