<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Password\Reset;

use Alaress\Module\Module as BaseModule;
use Alaress\Password\Service as PasswordService;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Router;
use Psr\Log\NullLogger;
use Schoolbox\Mail;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User\Password\MailService;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;

/**
 * Module for password resets
 *
 * @package Schoolbox\User\Password\Reset
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'userPasswordReset';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'displayCreateRequestForm', $basePath, '');
        $this->addPostRoute($router, 'createRequest', $basePath, '/createRequest');
        $this->addGetRoute($router, 'displayResetForm', $basePath, '/{token}')->addTokens(['token' => '[0-9a-f]+']);
        $this->addPostRoute($router, 'reset', $basePath, '/reset/{token}')->addTokens(['token' => '[0-9a-f]+']);
        $this->addGetRoute($router, 'displaySuccessMessage', $basePath, '/success');
    }

    /**
     * Route preloads - currently, loads token into the reset form display
     *
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(displayResetForm|reset)',
            function ($params) {
                try {
                    $token = $this->getMapper()->findById($params['token']);
                } catch (MapperException $e) {
                    $token = $this->getMapper()->createModel();
                }
                return ['reset' => $token,];
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(Mapper::class),
            new MailService(
                $this->getConfig(),
                $this->getMapperFactory()->get(Mapper::class),
                $this->getMapperFactory()->get(UserMapper::class),
                new Mail\TwigEmailFactory(
                    $this->getConfig(),
                    new NullLogger(),
                ),
            ),
            new PasswordService(),
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper
     */
    protected function getMapper()
    {
        /** @var Mapper $mapper */
        $mapper = $this->getMapperFactory()->get(Mapper::class);
        return $mapper;
    }

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
