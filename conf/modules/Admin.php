<?php

/**
 * @author      Damian SLoane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Filter\Module as FilterModule;

class Admin extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        if ($di->get('config')->get(ConfigMapper::NAME_LMS_ENABLED)) {
            // Admin course builder page
            $this->loadModule($di, \Schoolbox\Admin\CourseBuilder\Module::class, '/adminv2/course-builder');

            // Bulk course import
            $this->loadModule($di, \Schoolbox\Admin\BulkCourseImport\Module::class, '/adminv2/bulkCourseImport');
        }
        if ($di->get('config')->get(ConfigMapper::NAME_SUMMATIVE_REPORT_ENABLED)) {
            // Report export
            $this->loadModule($di, \Schoolbox\Admin\SummativeReport\Module::class, '/adminv2/summative-report');
        }

        // Eportfolio
        $this->loadModule($di, \Schoolbox\Admin\Eportfolio\Module::class, '/adminv2/eportfolio');

        // Feature Tours
        $this->loadModule($di, \Schoolbox\Admin\FeatureTours\Module::class, '/adminv2/feature-tours');

        // Admin Home
        $this->loadModule($di, \Schoolbox\Admin\Home\Module::class, '/adminv2/home');

        // Admin Lists
        $this->loadModule($di, \Schoolbox\Admin\Lists\Module::class, '/adminv2/lists');

        // LTI
        $this->loadModule($di, \Schoolbox\Admin\LTI\Module::class, '/adminv2/lti');

        // Side menu manager
        $this->loadModule($di, \Schoolbox\Admin\Menu\Side\Module::class, '/adminv2/menu-side');

        // Top menu manager
        $this->loadModule($di, \Schoolbox\Admin\Menu\Top\Module::class, '/adminv2/menu-top');

        // Profile button manager
        $this->loadModule($di, \Schoolbox\Admin\Profile\Button\Module::class, '/adminv2/profile-button');

        // Admin navigation
        $this->loadModule($di, \Schoolbox\Admin\Navigation\Module::class, '/adminv2/navigation');

        // oAuth provider settings
        $this->loadModule($di, \Schoolbox\Admin\Oauth\Module::class, '/adminv2/oauth');

        // oAuth client settings
        $this->loadModule($di, \Schoolbox\Admin\Oauth\Client\Module::class, '/adminv2/oauth/client');
        $this->loadModule($di, \Schoolbox\Admin\Oauth\Client\Api\Module::class, '/adminv2/oauth/client/api');

        // Route list
        $this->loadModule($di, \Schoolbox\Admin\RouteSummary\Module::class, '/adminv2/routes');

        // Dependency list
        $this->loadModule($di, \Schoolbox\Admin\DependencySummary\Module::class, '/adminv2/dependencies');

        // Service Roster
        $this->loadModule($di, \Schoolbox\Admin\Service\Module::class, '/adminv2/service');

        // Settings
        $this->loadModule($di, \Schoolbox\Admin\Settings\Module::class, '/adminv2/setting');

        // SHAPS
        $this->loadModule($di, \Schoolbox\Admin\SHAPS\Module::class, '/adminv2/shaps');

        // Package Installer
        $this->loadModule($di, \Schoolbox\Admin\Package\Module::class, '/adminv2/package');

        // Custom Terminology
        $this->loadModule($di, \Schoolbox\Admin\Terminology\Module::class, '/adminv2/terminology');

        // Admin terms manager page
        $this->loadModule($di, \Schoolbox\Admin\Terms\Module::class, '/adminv2/terms');

        // Admin Modify User
        $this->loadModule($di, \Schoolbox\Admin\User\Module::class, '/adminv2/user');

        // Admin Bulk Modify User
        $this->loadModule($di, \Schoolbox\Admin\User\BulkModify\Module::class, '/adminv2/user/bulk');

        // Video Queue
        $this->loadModule($di, \Schoolbox\Admin\Video\Module::class, '/adminv2/video');

        // News
        $this->loadModule($di, \Schoolbox\Admin\News\Policy\Module::class, '/adminv2/news');

        // Notifications
        $this->loadModule($di, \Schoolbox\Realtime\Admin\Module::class, '/adminv2/notifications');
        $this->loadModule($di, \Schoolbox\Realtime\Admin\Config\Module::class, '/adminv2/notifications/config');

        // Filters
        $this->loadModule($di, FilterModule::class, '/adminv2/filters');

        // Restore (undelete) entities that have been recently deleted
        $this->loadModule($di, \Schoolbox\Admin\Restore\Module::class, '/adminv2/restore');

        // Grades page
        $this->loadModule($di, \Schoolbox\Admin\Grades\Module::class, '/adminv2/grades');

        // Usage Graphs
        $this->loadModule($di, \Schoolbox\Admin\Usage\Module::class, '/adminv2/usage');

        // Intellischool
        $this->loadModule($di, \Schoolbox\Admin\Intellischool\Module::class, '/adminv2/intellischool');

        //Health Check
        $this->loadModule($di, \Schoolbox\Admin\HealthCheck\Module::class, '/adminv2/health-check-export');

        // Email DKIM
        $this->loadModule($di, \Schoolbox\Admin\EmailDKIM\Module::class, '/adminv2/email-dkim');

        // Rubrics
        $this->loadModule($di, \Schoolbox\Admin\RubricResultSet\Module::class, '/adminv2/rubric-result-set');

        // Lesson Plan Template
        $this->loadModule($di, \Schoolbox\Admin\LessonPlan\Template\Module::class, '/adminv2/lesson-plan/template');

        /* Profile Editor
         * This lets you configure what appears on each role type's Profile screen
         */
        $this->loadModule($di, \Schoolbox\Admin\Profile\Editor\Tab\Module::class, '/adminv2/profile-editor/tab');
        $this->loadModule($di, \Schoolbox\Admin\Profile\Editor\Field\Module::class, '/adminv2/profile-editor/field');
        $this->loadModule($di, \Schoolbox\Admin\Profile\Editor\Module::class, '/adminv2/profile-editor');

        /* Calendar Import
         */
        $this->loadModule($di, \Schoolbox\Admin\CalendarImport\Module::class, '/adminv2/calendar');

        /* Bulk Feedback Import
         */
        $this->loadModule($di, \Schoolbox\Admin\BulkFeedbackImport\Module::class, '/adminv2/bulk-feedback-import');

        /* OpenAI
         */
        $this->loadModule($di, \Schoolbox\Admin\OpenAI\Module::class, '/adminv2/ai-services');
        $this->loadModule($di, \Schoolbox\Admin\OpenAI\Usage\Module::class, '/adminv2/aiLog');

        /* Editor Templates
         */
        $this->loadModule($di, \Schoolbox\Admin\Editor\Templates\Module::class, '/adminv2/editor-templates');

        /* Learner Profiles
         */
        $this->loadModule($di, \Schoolbox\Admin\DifferentiationProfiles\Module::class, '/adminv2/differentiation/differentiation-profiles');

        /* Data Retention
         */
        $this->loadModule($di, \Schoolbox\Admin\DataRetention\Module::class, '/adminv2/data-retention');
    }
}
