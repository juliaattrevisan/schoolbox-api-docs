<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\Assessment\Course;
use Schoolbox\Learning\Assessment\Calendar\Module as AssessmentCalendarModule;
use Schoolbox\Learning\Assessment\Exporter\Module as AssessmentExporterModule;
use Schoolbox\Learning\Assessment\Module as AssessmentModule;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Module as DueworkFeedbackModule;
use Schoolbox\Learning\Assessment\DueWork\Feedback\File\Module as DueworkFeedbackFileModule;
use Schoolbox\Learning\Assessment\DueWork\Response\Module as DueworkResponseModule;
use Schoolbox\Learning\Grades;
use Schoolbox\Learning\Markbook\Module as MarkbookModule;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Learning\Module as LearningModule;
use Schoolbox\Learning\Quiz\Module as QuizModule;
use Schoolbox\Learning\Upcoming;

class Learning extends ModuleLoader
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function configure($kernel): void
    {
        $namespace  = 'learning';

        // TODO - split 'LearningModule' into ClassModule + PendingModule
        $this->loadModule($kernel, LearningModule::class, "/{$namespace}");

        $this->loadModule($kernel, Grades\Module::class, "/{$namespace}/grades");
        $this->loadModule($kernel, AssessmentModule::class, "/{$namespace}");
        $this->loadModule($kernel, DueworkFeedbackModule::class, "/{$namespace}");
        $this->loadModule($kernel, DueworkFeedbackFileModule::class, "/{$namespace}/feedback");
        $this->loadModule($kernel, DueworkResponseModule::class, "/{$namespace}");
        $this->loadModule($kernel, QuizModule::class, "/{$namespace}");
        $this->loadModule($kernel, MarkbookModule::class, "/{$namespace}/markbook");
        $this->loadModule($kernel, Course\Markbook\Module::class, "/{$namespace}/courseMarkbook");
        $this->loadModule($kernel, Upcoming\Module::class, "/{$namespace}/due");
        $this->loadModule($kernel, AssessmentExporterModule::class, "/{$namespace}/export");
        $this->loadModule($kernel, MarkScheme\Module::class, "/{$namespace}/markscheme");

        /* Assessment Calendar is behind a feature flag
         */
        if ($kernel->getDependencyInjector()->get('config')->get('assessment_calendar_enabled')) {
            $this->loadModule($kernel, AssessmentCalendarModule::class, "/{$namespace}/assessment/calendar");
        }
    }
}
