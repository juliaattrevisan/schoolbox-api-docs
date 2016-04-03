<?php
/**
 * Configuration for the tile component layout.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Component_InstanceMapper;
use External_User;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Tile\Mapper;
use Schoolbox\Tile\Module;

class Tile implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     *
     * @return void
     */
    public function configure($kernel)
    {
        $module = new Module($kernel->getDependencyInjector());
        $module
            ->setMapper(Mapper::getInstance())
            ->setCategoryMapper(FolderMapper::getInstance())
            ->setComponentInstanceMapper(Component_InstanceMapper::getInstance())
            ->setRoleAccessMapper(RoleAccessMapper::getInstance())
            ->setExternalDb(new External_User());
        $module->init('/tile');
    }
}
