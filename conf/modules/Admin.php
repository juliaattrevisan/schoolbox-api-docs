<?php
/**
 * 
 * @author      Damian SLoane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        // Share navigation element

        $navigation = new \Schoolbox\Admin\Navigation\Module($di);
        $navigation->init('/adminv2/navigation');

        // Messages

        $messagesModule = new \Schoolbox\Realtime\Admin\Module($di);
        $messagesModule->init('/adminv2/messages');

        // Messages

        $modifyUser = new \Schoolbox\Admin\User\Module($di);
        $modifyUser->init('/adminv2/user');

        // Admin home

        $home = new \Schoolbox\Admin\Home\Module($di);
        $home->init('/adminv2/home');

        // Admin lists

        $lists = new \Schoolbox\Admin\Lists\Module($di);
        $lists->init('/adminv2/lists');

        // Eporfolio

        $lists = new \Schoolbox\Admin\Eportfolio\Module($di);
        $lists->init('/adminv2/eportfolio');

        // LTI

        $lti = new \Schoolbox\Admin\LTI\Module($di);
        $lti->init('/adminv2/lti');

        // Admin cleanup page

        $clean = new \Schoolbox\Admin\Clean\Module($di);
        $clean->init('/adminv2/cleanup');

        // Admin course builder page

        $courseBuilder = new \Schoolbox\Admin\CourseBuilder\Module($di);
        $courseBuilder->init('/adminv2/course-builder');

        // Custom Terminology

        $terminology = new \Schoolbox\Admin\Terminology\Module($di);
        $terminology->init('/adminv2/terminology');

        // Admin course builder page

        $photos = new \Schoolbox\Admin\Photos\Module($di);
        $photos->init('/adminv2/photos');

        // Admin bulk add folders

        $bulkAddFolders = new \Schoolbox\Admin\BulkAddFolders\Module($di);
        $bulkAddFolders->init('/adminv2/bulk-add-folders');

        // Admin terms manager page
        $terms = new \Schoolbox\Admin\Terms\Module($di);
        $terms->init('/adminv2/terms');

        // Top menu manager
        $menuTop = new \Schoolbox\Admin\Menu\Top\Module($di);
        $menuTop->init('/adminv2/menu-top');

        // Side menu manager
        $menuSide = new \Schoolbox\Admin\Menu\Side\Module($di);
        $menuSide->init('/adminv2/menu-side');

        // Pastoral care import page

        if ($di->get('config')->get('pastoral_enabled')) {
            $courseBuilder = new \Schoolbox\Admin\Import\Pastoral\Module($di);
            $courseBuilder->init('/adminv2/import-pastoral');
        }

        // Route list
        $routes = new \Schoolbox\Admin\RouteSummary\Module($di);
        $routes->init('/adminv2/routes');

        // Video Queue
        $video = new \Schoolbox\Admin\Video\Module($di);
        $video->init('/adminv2/video');

        // Filters
        $filters = new FilterModule($di);
        $filters->init('/adminv2/filters');

        //Feature Tours
        $featureTours = new \Schoolbox\Admin\FeatureTours\Module($di);
        $featureTours->init('/adminv2/feature-tours');
    }
} 
