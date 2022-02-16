<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\MyFiles;

use Alaress\Web\Controller\Base;
use Schoolbox\Resource\Browser\FullAccessController;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Resource\My;
use Schoolbox\Resource\Browser\AbstractModule;
use Schoolbox\UserMapper;

class Module extends AbstractModule
{
    protected $basename = 'myFilesBrowser';

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return (new FullAccessController())
            ->setFileMapper($this->getMapperFactory()->get(My\File\Mapper::class))
            ->setConfig($this->getConfig())
            ->setUserMapper($this->getMapperFactory()->get(UserMapper::class))
            ->setNodeFactory(new NodeFactory())
            ->setTreeBuilder(
                new TreeBuilder(
                    $this->getMapperFactory()->get(My\Folder\Mapper::class),
                    $this->injector->get('user'),
                )
            )
            ->setFolderMapper($this->getMapperFactory()->get(My\Folder\Mapper::class))
            ->setNamespace($this->basename)
            ->setNodeType(NodeFactory::MYFILE)
            ->setTab('myfiles');
    }
}
