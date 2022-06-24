<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\WebDav;

use Alaress\Web\Controller\Base;
use PortfolioWebDAV;
use Schoolbox\Resource\Browser\AbstractModule;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\UserMapper;

class Module extends AbstractModule
{
    protected $basename = 'webDavBrowser';

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $session = $this->injector->get('session');
        if (!$session->isStarted()) {
            $session->start();
        }
        try {
            $dav = PortfolioWebDAV::factory();
        } catch (\UnexpectedValueException $e) {
            $dav = null;
        }
//        catch (\DomainException $e) {
//            /**
//             * @var ErrorMapper $errorMapper
//             */
//            $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);
//            throw $errorMapper->generateException(ErrorMapper::ERROR_WEBDAV, $e, $e->getMessage());
//        }

        return (new Controller())
            ->setFileMapper($dav)
            ->setConfig($this->getConfig())
            ->setUserMapper($this->getMapperFactory()->get(UserMapper::class))
            ->setNodeFactory(new NodeFactory())
            ->setTreeBuilder(new TreeBuilder($dav))
            ->setFolderMapper($dav)
            ->setNamespace($this->basename)
            ->setNodeType(NodeFactory::WEBDAV)
            ->setFilesystemProxy($this->injector->get('filesystem.storageProxy'));
    }
}
