<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Assessment\Course;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\Calendar\Events\Module as AssessmentCalendarEventsModule;
use Schoolbox\Learning\Assessment\Calendar\Module as AssessmentCalendarModule;
use Schoolbox\Learning\Assessment\DueWork\Feedback\File\Module as DueworkFeedbackFileModule;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Module as DueworkFeedbackModule;
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
use Schoolbox\Learning\Turnitin\Module as TurnitinModule;
use Schoolbox\Learning\Upcoming;

class Learning extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        /** @var IConfigLoader $config */
        $config = $di->get('config');

        $namespace = 'learning';

        // TODO - split 'LearningModule' into ClassModule + PendingModule
        $this->loadModule($di, LearningModule::class, "/{$namespace}");

        // LMS module required plan (Basic,Pro,Elite)
        if ($config->get(ConfigMapper::NAME_LMS_ENABLED)) {
            $this->loadModule($di, Grades\Module::class, "/{$namespace}/grades");
            $this->loadModule($di, AssessmentModule::class, "/{$namespace}");
            $this->loadModule($di, DueworkFeedbackModule::class, "/{$namespace}");
            $this->loadModule($di, DueworkFeedbackFileModule::class, "/{$namespace}/feedback");
            $this->loadModule($di, DueworkResponseModule::class, "/{$namespace}");
//            $this->loadModule($di, QuizModule::class, "/{$namespace}");

            // Construct quiz module with a different signature
            $quizModule = new QuizModule($di, null);
            $quizModule->init("/$namespace");

            $this->loadModule($di, MarkbookModule::class, "/{$namespace}/markbook");
            $this->loadModule($di, Course\Markbook\Module::class, "/{$namespace}/courseMarkbook");
            $this->loadModule($di, Upcoming\Module::class, "/{$namespace}/due");
            $this->loadModule($di, AssessmentExporterModule::class, "/{$namespace}/export");
            $this->loadModule($di, MarkScheme\Module::class, "/{$namespace}/markscheme");
        }

        // Student toolkit component: not feature flagged
        $this->loadModule($di, StudentToolkitModule::class, "/{$namespace}/studentToolkit");


        // Learning moment evidence: behind a feature flag
        if ($config->get(ConfigMapper::NAME_LEARNING_MOMENTS_ENABLED)) {
            $this->loadModule($di, EvidenceModule::class, "/{$namespace}/evidence");

            // Discussion threads and comments for learning moment evidence:
            // also behind the same feature flag
            $this->loadModule($di, EvidenceThreadModule::class, "/discussion/evidence/{contextId}/threads");
            $this->loadModule($di, EvidenceCommentModule::class, "/discussion/evidence/{contextId}/comments");
        }

        // Badge module: behind a feature flag
        if ($config->get(ConfigMapper::NAME_BADGES_ENABLED)) {
            $this->loadModule($di, BadgeModule::class, "/{$namespace}/badge");
            $this->loadModule($di, BadgeAwardModule::class, "/{$namespace}/badge/award");
        }

        // Feed module: not behind a feature flag
        $this->loadModule($di, FeedModule::class, "/{$namespace}/evidenceFeed");

        /* Assessment Calendar is behind a feature flag
         */
        if ($config->get('assessment_calendar_enabled')) {
            $this->loadModule($di, AssessmentCalendarModule::class, "/{$namespace}/assessment/calendar");
            $this->loadModule(
                $di,
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
        $this->loadModule($di, TurnitinModule::class, "/{$namespace}/turnitin");
    }
}
