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
use Schoolbox\Learning\Mastery\Module as MasteryModule;
use Schoolbox\Learning\Assessment\Module as AssessmentModule;
use Schoolbox\Learning\Feedback\Module as FeedbackModule;
use Schoolbox\Learning\Quiz\Module as QuizModule;
use Schoolbox\Learning\Response\Module as ResponseModule;
use Schoolbox\Learning\Upcoming\Module as UpcomingModule;
use Schoolbox\Learning\Assessment\Exporter\Module as AssessmentExporterModule;


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
        $namespace  = 'learning';
        $di = $kernel->getDependencyInjector();

        $learningModule = new LearningModule($di);
        $learningModule->init("/$namespace");

        $assessmentModule = new AssessmentModule($di);
        $assessmentModule->init("/$namespace");

        $feedbackModule = new FeedbackModule($di);
        $feedbackModule->init("/$namespace");

        $responseModule = new ResponseModule($di);
        $responseModule->init("/$namespace");

        $quizModule = new QuizModule($di);
        $quizModule->init("/$namespace");

        $markbookModule = new MarkbookModule($di);
        $markbookModule ->init("/$namespace/markbook");

        $masteryModule = new MasteryModule($di);
        $masteryModule ->init("/$namespace/mastery");

        $upcomingModule = new UpcomingModule($di);
        $upcomingModule->init("/$namespace/due");

        $assessmentExporterModule = new AssessmentExporterModule($di);
        $assessmentExporterModule->init("/$namespace/export");
    }
}
