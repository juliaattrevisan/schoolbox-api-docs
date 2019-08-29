<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser\WebDav;


use Aura\Di\Container;
use PortfolioWebDAV;
use Schoolbox\Resource\Browser\AbstractModule;
use Schoolbox\Resource\Browser\FullAccessController;
use Schoolbox\Resource\Browser\NodeFactory;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Application\Exception\Exception as SchoolboxException;


class Module extends AbstractModule
{
    protected $basename = 'webDavBrowser';

    /**
     * @param Container $ci
     * @return array
     * @throws SchoolboxException
     */
    protected function getSetterList(Container $ci)
    {
        $lazyDav = $ci->lazy(function () use ($ci) {
            // open session first, because webdav factory needs session to
            // determine user's permissions
            /** @var \Alaress\Session\Session $session */
            $session = $ci->get('session');
            if (!$session->isStarted()) {
                $session->start();
            }
            try {
                return PortfolioWebDAV::factory();
            } catch (\UnexpectedValueException $e) {
                return null;
            } catch (\DomainException $e) {
                /**
                 * @var ErrorMapper $errorMapper
                 */
                $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);
                throw $errorMapper->generateException(ErrorMapper::ERROR_WEBDAV, $e, $e->getMessage());
            }
        });

        return array_merge(parent::getSetterList($ci), [
            'setTreeBuilder'  => $ci->lazyNew(TreeBuilder::class, ['dav' => $lazyDav,]),
            'setNodeType'     => NodeFactory::WEBDAV,
            'setFolderMapper' => $lazyDav,
            'setFileMapper'   => $lazyDav,
        ]);
    }


    /**
     * @param Container $injector
     * @return void
     */
    protected function initSetters(Container $injector)
    {
        $injector->setters[$this->getControllerClass()]['setTab'] = 'network';
    }


    /**
     * @return string
     */
    protected function getControllerClass()
    {
        return FullAccessController::class;
    }

}
