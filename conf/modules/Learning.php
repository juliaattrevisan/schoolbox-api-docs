<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Schoolbox\Assessment\Course;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\Calendar\Events\Module as AssessmentCalendarEventsModule;
use Schoolbox\Learning\Assessment\Calendar\Module as AssessmentCalendarModule;
use Schoolbox\Learning\Assessment\DueWork\Feedback\File\Module as DueworkFeedbackFileModule;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Module as DueworkFeedbackModule;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Module as TurnitinModule;
use Schoolbox\Learning\Assessment\DueWork\Response\Module as DueworkResponseModule;
use Schoolbox\Learning\Assessment\Exporter\Module as AssessmentExporterModule;
use Schoolbox\Learning\Assessment\Module as AssessmentModule;
use Schoolbox\Learning\Badge\Award\Module as BadgeAwardModule;
use Schoolbox\Learning\Badge\Module as BadgeModule;
use Schoolbox\Learning\Grades;
use Schoolbox\Learning\Markbook\Module as MarkbookModule;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Learning\Module as LearningModule;
use Schoolbox\Learning\Moment\Evidence\Discussion\Comment\Module as EvidenceCommentModule;
use Schoolbox\Learning\Moment\Evidence\Discussion\Thread\Module as EvidenceThreadModule;
use Schoolbox\Learning\Moment\Evidence\Module as EvidenceModule;
use Schoolbox\Learning\Moment\Feed\Module as FeedModule;
use Schoolbox\Learning\Moment\StudentToolkit\Module as StudentToolkitModule;
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
        /** @var IConfigLoader $config */
        $config = $kernel->getDependencyInjector()->get('config');

        $namespace = 'learning';

        // TODO - split 'LearningModule' into ClassModule + PendingModule
        $this->loadModule($kernel, LearningModule::class, "/{$namespace}");

        // LMS module required plan (Basic,Pro,Elite)
        if ($config->get(ConfigMapper::NAME_LMS_ENABLED)) {
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
        }

        // Student toolkit component: not feature flagged
        $this->loadModule($kernel, StudentToolkitModule::class, "/{$namespace}/studentToolkit");


        // Learning moment evidence: behind a feature flag
        if ($config->get(ConfigMapper::NAME_LEARNING_MOMENTS_ENABLED)) {
            $this->loadModule($kernel, EvidenceModule::class, "/{$namespace}/evidence");

            // Discussion threads and comments for learning moment evidence:
            // also behind the same feature flag
            $this->loadModule($kernel, EvidenceThreadModule::class, "/discussion/evidence/{contextId}/threads");
            $this->loadModule($kernel, EvidenceCommentModule::class, "/discussion/evidence/{contextId}/comments");
        }

        // Badge module: behind a feature flag
        if ($config->get(ConfigMapper::NAME_BADGES_ENABLED)) {
            $this->loadModule($kernel, BadgeModule::class, "/{$namespace}/badge");
            $this->loadModule($kernel, BadgeAwardModule::class, "/{$namespace}/badge/award");
        }

        // Feed module: not behind a feature flag
        $this->loadModule($kernel, FeedModule::class, "/{$namespace}/evidenceFeed");

        /* Assessment Calendar is behind a feature flag
         */
        if ($config->get('assessment_calendar_enabled')) {
            $this->loadModule($kernel, AssessmentCalendarModule::class, "/{$namespace}/assessment/calendar");
            $this->loadModule(
                $kernel,
                AssessmentCalendarEventsModule::class,
                "/{$namespace}/assessment/calendar/events"
            );
        }

        /* Turnitin webhook controller
         *
         * TODO optional load
         *  make webhooks accessible if your turnitin configuration exists
         *  and based on plan availability settings?
         */
        $this->loadModule($kernel, TurnitinModule::class, "/{$namespace}/turnitin");
    }
}
