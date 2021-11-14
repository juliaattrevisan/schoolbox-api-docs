<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Platform\Context;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\Resource\Folder\Model;

/**
 * LTI Platform sub-module for contexts.
 *
 * The standard context for LTI assessments is the folder.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'ltiPlatformContext';

    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        $this->register(
            self::METHOD_GET,
            '/folder/{folderId}/memberships',
            'getMembershipsForFolder',
            null,
            function (array $params) {
                try {
                    return ['folder' => $this->getMapperFactory()->get(Mapper::class)->findById($params['folderId'])];
                } catch (MapperException $e) {
                    throw new NotFound($e->getMessage());
                }
            },
        );
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'keyChains'         => $injector->lazyGet('lti.keyChains'),
                    'registrations'     => $injector->lazyGet('lti.registrations'),
                    'userAuthenticator' => $injector->lazyGet('lti.userAuthenticator'),
                    'instance'          => $injector->lazyGet('instance'),
                    'userCodec'         => $injector->lazyGet('lti.userCodec'),
                    'accessTokenCache'  => $injector->lazyGet('lti.accessTokenCache'),
                    'userAccessMapper'  => $this->getMapperFactory()->getLazy(UserAccessMapper::class),
                    'access'            => $this->getMapperFactory()->getLazy(Access::class),
                ]
            )
        );
    }
}
