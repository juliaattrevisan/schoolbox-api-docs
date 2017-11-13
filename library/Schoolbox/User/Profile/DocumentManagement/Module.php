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
use Alaress\Twig\Loader\IChainLoader;

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
        $preloader = function ($params) {
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
        };

        $permission = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasRole(Role::STAFF),
            new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'studentId')
        );

        $this->register(
            'GET',
            '/{studentId}',
            'getIndex',
            $permission,
            $preloader
        );

        $this->register(
            'GET',
            '/{studentId}/download/{documentId}/source/{source}',
            'getDownload',
            $permission,
            $preloader
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, [
            'externalDocument' => $injector->lazyGet('externalDocumentManager'),
        ]));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
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
