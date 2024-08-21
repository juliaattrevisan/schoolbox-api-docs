<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\User\Password\Reset;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Rule\Callback;
use Alaress\Module\Module as BaseModule;
use Alaress\Password\Service as PasswordService;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Map;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User\Password\MailService;
use Schoolbox\UserMapper;
use Alaress\Twig\Loader\IChainLoader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Exception\RfcComplianceException;

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
    public function initRoutes(string $basePath)
    {
        $this->addGetRoute('displayCreateRequestForm', $basePath, '');
        $this->addPostRoute('createRequest', $basePath, '/createRequest');
        $this->addGetRoute('displayResetForm', $basePath, '/{token}')->tokens(['token' => '[0-9a-f]+']);
        $this->addPostRoute('reset', $basePath, '/reset/{token}')->tokens(['token' => '[0-9a-f]+']);
        $this->addGetRoute('displaySuccessMessage', $basePath, '/success');
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
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission(
            $authorizer,
            '.*',
            new Callback(function (): void {
                try {
                    new Address($this->getConfig()->get(ConfigMapper::NAME_MAIL_PASSWORD_CHANGE_FROM));
                } catch (RfcComplianceException | LogicException) {
                    throw new Forbidden('This service is currently unavailable.');
                }
            })
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
                $this->injector->get('mail.emailFactory'),
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
