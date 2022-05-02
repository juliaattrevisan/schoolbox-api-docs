<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox;

// TODO - this class name is illegal in PHP7. Needs to be changed to other than 'Resource'
class Resource implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $browserModule = new Schoolbox\Resource\Browser\Resources\Module($di);
        $browserModule->registerModule('/resources');
        $browserModule->init('/resources');

        $myBrowserModule = new Schoolbox\Resource\Browser\MyFiles\Module($di);
        $myBrowserModule->registerModule('/my-files');
        $myBrowserModule->init('/my-files');

        $webDavBrowserModule = new Schoolbox\Resource\Browser\WebDav\Module($di);
        $webDavBrowserModule->registerModule('/drives');
        $webDavBrowserModule->init('/drives');

        $folderModule = new Schoolbox\Resource\Folder\Module($di);
        $folderModule->registerModule('/resource');
        $folderModule->init('/resource');

        $textboxModule = new Schoolbox\Resource\Textbox\Module($di);
        $textboxModule->registerModule('/textbox');
        $textboxModule->init('/textbox');

        $googleFileModule = new Schoolbox\Resource\GoogleFile\Module($di);
        $googleFileModule->registerModule('/googleFile');
        $googleFileModule->init('/googleFile');
        // Commented out as:
        // * it's unused
        // * it collides with folder search page www/resource/folder/search.php
//        $folderSearchModule = new \Schoolbox\Resource\Folder\Search\Module($di);
//        $folderSearchModule->init('/resource/folder/search');
    }
}
