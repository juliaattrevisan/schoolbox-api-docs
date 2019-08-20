<?php
/**
 * @author      Damian SLoane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Filter\Module as FilterModule;

class Admin implements ModuleConfigInterface
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        // Share navigation element
        (new \Schoolbox\Admin\Navigation\Module($di))->init('/adminv2/navigation');

        // Messages
        (new \Schoolbox\Realtime\Admin\Module($di))->init('/adminv2/messages');

        // Admin Modify User
        (new \Schoolbox\Admin\User\Module($di))->init('/adminv2/user');

        // Admin Home
        (new \Schoolbox\Admin\Home\Module($di))->init('/adminv2/home');

        // Admin Lists
        (new \Schoolbox\Admin\Lists\Module($di))->init('/adminv2/lists');

        // Eporfolio
        (new \Schoolbox\Admin\Eportfolio\Module($di))->init('/adminv2/eportfolio');

        // LTI
        (new \Schoolbox\Admin\LTI\Module($di))->init('/adminv2/lti');

        // Admin course builder page
        (new \Schoolbox\Admin\CourseBuilder\Module($di))->init('/adminv2/course-builder');

        // Custom Terminology
        (new \Schoolbox\Admin\Terminology\Module($di))->init('/adminv2/terminology');

        // Admin bulk add folders
        (new \Schoolbox\Admin\BulkAddFolders\Module($di))->init('/adminv2/bulk-add-folders');

        // Admin terms manager page
        (new \Schoolbox\Admin\Terms\Module($di))->init('/adminv2/terms');

        // Top menu manager
        (new \Schoolbox\Admin\Menu\Top\Module($di))->init('/adminv2/menu-top');

        // Side menu manager
        (new \Schoolbox\Admin\Menu\Side\Module($di))->init('/adminv2/menu-side');

        // Profile button manager
        (new \Schoolbox\Admin\Profile\Button\Module($di))->init('/adminv2/profile-button');

        // Route list
        (new \Schoolbox\Admin\RouteSummary\Module($di))->init('/adminv2/routes');

        // Video Queue
        (new \Schoolbox\Admin\Video\Module($di))->init('/adminv2/video');

        // Filters
        (new FilterModule($di))->init('/adminv2/filters');

        //Feature Tours
        (new \Schoolbox\Admin\FeatureTours\Module($di))->init('/adminv2/feature-tours');

        // Service Roster
        (new \Schoolbox\Admin\Service\Module($di))->init('/adminv2/service');

        // SHAPS
        (new \Schoolbox\Admin\SHAPS\Module($di))->init('/adminv2/shaps');

        // News
        (new \Schoolbox\Comms\News\Policy\Module($di))->init('/adminv2/news');

        // Settings
        (new \Schoolbox\Admin\Settings\Module($di))->init('/adminv2/setting');

        // oAuth-specific settings
        (new \Schoolbox\Admin\Oauth\Module($di))->init('/adminv2/oauth');

        /**
         * Importers
         */

        /* User Photos
         */
        (new \Schoolbox\Admin\Photos\Module($di))->init('/adminv2/photos');

        /* Pastoral Care
         */
        if ($di->get('config')->get('pastoral_enabled')) {
            (new \Schoolbox\Admin\Import\Pastoral\Module($di))->init('/adminv2/import-pastoral');
        }
    }
}
