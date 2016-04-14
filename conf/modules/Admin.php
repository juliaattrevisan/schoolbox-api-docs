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
use Schoolbox\Assessment\Term;
use Schoolbox\Realtime\Admin\Module;

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

        $messagesModule = new Module($kernel->getDependencyInjector());
        $messagesModule->init('/adminv2/messages');

        // Admin home

        $home = new \Schoolbox\Admin\Home\Module($di);
        $home->init('/adminv2/home');

        // Admin lists

        $lists = new \Schoolbox\Admin\Lists\Module($di);
        $lists->init('/adminv2/lists');

        // Admin cleanup page

        $clean = new \Schoolbox\Admin\Clean\Module($di);
        $clean->init('/adminv2/cleanup');

        // Admin course builder page

        $courseBuilder = new \Schoolbox\Admin\CourseBuilder\Module($di);
        $courseBuilder->init('/adminv2/course-builder');

        // Admin course builder page

        $photos = new \Schoolbox\Admin\Photos\Module($di);
        $photos->init('/adminv2/photos');

        // Admin bulk add folders

        $bulkAddFolders = new \Schoolbox\Admin\BulkAddFolders\Module($di);
        $bulkAddFolders->init('/adminv2/bulk-add-folders');

        // Admin terms manager page
        $terms = new \Schoolbox\Admin\Terms\Module($di, Term\Mapper::getInstance());
        $terms->init('/adminv2/terms');

        // Pastoral care import page

        if ($di->get('config')->get('pastoral_enabled')) {
            $courseBuilder = new \Schoolbox\Admin\Import\Pastoral\Module($di);
            $courseBuilder->init('/adminv2/import-pastoral');
        }

    }
} 