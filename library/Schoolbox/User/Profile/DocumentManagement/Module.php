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
                '/{studentId}' => ['getIndex' => $permissionStaff],
                '/{studentId}/download/{documentId}' => ['getDownload' => $permissionStaff],
            ],
            'POST' => [
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, [
            'userMapper' => UserMapper::getInstance(),
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
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

            $injectedValues = array_merge($injectedValues, $params);

            return $injectedValues;
        });
    }


    /**
     * These pages may be accessed by:
     *
     * * superusers
     * * staff
     * * the child themselves
     * * the child's parents
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $ruleRegexp = '(?:getIndex|getDownload)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                $ruleRegexp,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'studentId')
                )
            );
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


    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        /** @var UserMapper $userMapper */
        $userMapper = $this->getMapperFactory()->get(UserMapper::class);

        return $userMapper;
    }
}
