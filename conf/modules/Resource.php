<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox;

// TODO - this class name is illegal in PHP7. Needs to be changed to other than 'Resource'
class Resource implements ModuleConfigInterface
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

        $browserModule = new Schoolbox\Resource\Browser\Resources\Module($di);
        $browserModule->init('/resources');

        $myBrowserModule = new Schoolbox\Resource\Browser\MyFiles\Module($di);
        $myBrowserModule->init('/my-files');

        $webDavBrowserModule = new Schoolbox\Resource\Browser\WebDav\Module($di);
        $webDavBrowserModule->init('/drives');

        $folderModule = new \Schoolbox\Resource\Folder\Module($di);
        $folderModule->init('/resource');

        $folderSearchModule = new \Schoolbox\Resource\Folder\Search\Module($di);
        $folderSearchModule->init('/resource/folder/search');
    }
}
