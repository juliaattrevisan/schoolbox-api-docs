<?php

/**
 * @author      Damian SLoane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Filter\Module as FilterModule;

class Admin implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        if ($di->get('config')->get(ConfigMapper::NAME_LMS_ENABLED)) {
            // Admin course builder page
            (new \Schoolbox\Admin\CourseBuilder\Module($di))->init('/adminv2/course-builder');

            // Bulk course import
            (new \Schoolbox\Admin\BulkCourseImport\Module($di))->init('/adminv2/bulkCourseImport');

            // Report export
            (new \Schoolbox\Admin\ReportExport\Module($di))->init('/adminv2/report-export');
        }

        // Eportfolio
        (new \Schoolbox\Admin\Eportfolio\Module($di))->init('/adminv2/eportfolio');

        // Feature Tours
        (new \Schoolbox\Admin\FeatureTours\Module($di))->init('/adminv2/feature-tours');

        // Admin Home
        (new \Schoolbox\Admin\Home\Module($di))->init('/adminv2/home');

        // Admin Lists
        (new \Schoolbox\Admin\Lists\Module($di))->init('/adminv2/lists');

        // LTI
        (new \Schoolbox\Admin\LTI\Module($di))->init('/adminv2/lti');

        // Side menu manager
        (new \Schoolbox\Admin\Menu\Side\Module($di))->init('/adminv2/menu-side');

        // Top menu manager
        (new \Schoolbox\Admin\Menu\Top\Module($di))->init('/adminv2/menu-top');

        // Profile button manager
        (new \Schoolbox\Admin\Profile\Button\Module($di))->init('/adminv2/profile-button');

        // Admin navigation
        (new \Schoolbox\Admin\Navigation\Module($di))->init('/adminv2/navigation');

        // oAuth-specific settings
        (new \Schoolbox\Admin\Oauth\Module($di))->init('/adminv2/oauth');

        // Route list
        (new \Schoolbox\Admin\RouteSummary\Module($di))->init('/adminv2/routes');

        // Service Roster
        (new \Schoolbox\Admin\Service\Module($di))->init('/adminv2/service');

        // Settings
        (new \Schoolbox\Admin\Settings\Module($di))->init('/adminv2/setting');

        // SHAPS
        (new \Schoolbox\Admin\SHAPS\Module($di))->init('/adminv2/shaps');

        // Package Installer
        (new \Schoolbox\Admin\Package\Module($di))->init('/adminv2/package');

        // Custom Terminology
        (new \Schoolbox\Admin\Terminology\Module($di))->init('/adminv2/terminology');

        // Admin terms manager page
        (new \Schoolbox\Admin\Terms\Module($di))->init('/adminv2/terms');

        // Admin Modify User
        (new \Schoolbox\Admin\User\Module($di))->init('/adminv2/user');

        // Admin Bulk Modify User
        (new \Schoolbox\Admin\User\BulkModify\Module($di))->init('/adminv2/user/bulk');

        // Video Queue
        (new \Schoolbox\Admin\Video\Module($di))->init('/adminv2/video');

        // News
        (new \Schoolbox\Admin\News\Policy\Module($di))->init('/adminv2/news');

        // Messages
        (new \Schoolbox\Realtime\Admin\Module($di))->init('/adminv2/messages');

        // Filters
        (new FilterModule($di))->init('/adminv2/filters');

        // Restore (undelete) entities that have been recenetly deleted
        (new \Schoolbox\Admin\Restore\Module($di))->init('/adminv2/restore');

        // Grades page
        (new \Schoolbox\Admin\Grades\Module($di))->init('/adminv2/grades');

        // Usage Graphs
        (new \Schoolbox\Admin\Usage\Module($di))->init('/adminv2/usage');

        // Usage Graphs
        (new \Schoolbox\Admin\Intellischool\Module($di))->init('/adminv2/intellischool');
    }
}
