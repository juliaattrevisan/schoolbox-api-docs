<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\User\Profile\DocumentManagement;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use Schoolbox\Error\Mapper as ErrorMapper;
use Twig_Loader_Chain;

// These mappers are injected into controllers where required

use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @var string $basename Module basename
     */
    protected $basename = 'profileDocumentManagement';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes()
    {
        $permissionStaff = new Rule\HasRole(Role::STAFF);

        $this->registerRoutesArray([
            'GET' => [
                '/{studentId}'                              => ['getIndex' => $permissionStaff],
            ],
            'POST' => [
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector) {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class, [
            'userMapper' => UserMapper::getInstance(),
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader) {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload($matcher, '.*', function ($params) {
            $injectedValues = [];

            if (array_key_exists('studentId', $params)) {
                try {
                    /** @var UserMapper $userMapper */
                    $userMapper = $this->getMapperFactory()->get(UserMapper::class);
                    $injectedValues['student'] = $userMapper->findById($params['studentId']);
                } catch (MapperException $e) {
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                }
            }

            return $injectedValues;
        });
    }

    /**
     * @return ErrorMapper
     * @throws InvalidArgumentException If cannot find mapper class
     */
    protected function getErrorMapper()
    {
        /** @var ErrorMapper $errorMapper */
        $errorMapper = $this->getMapperFactory()->get(ErrorMapper::class);

        return $errorMapper;
    }
}
