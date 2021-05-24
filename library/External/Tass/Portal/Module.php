<?php

/**
 * TASS portal module
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace External\Tass\Portal;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\IConfigLoader;
use Schoolbox\UserMapper;

class Module extends \Alaress\Module\Module
{
    protected $basename = 'tassportal';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addRoute($router, 'parentLounge', $basePath, '/parentlounge');
        $this->addRoute($router, 'tassWeb', $basePath, '/tassweb');
        $this->addRoute($router, 'teacherKiosk', $basePath, '/teacherkiosk');
        $this->addRoute($router, 'studentProfile', $basePath, '/studentProfile/{studentId}');
        $this->addRoute($router, 'webBook', $basePath, '/webbook');
        $this->addRoute($router, 'studentCafe', $basePath, '/studentcafe');
    }

    /**
     * @param PathMatcher $matcher
     */
    public function initRoutePreloads(PathMatcher $matcher): void
    {
        $this
            ->addRoutePreload($matcher, 'studentProfile', function ($params) {
                return [
                    'student' => UserMapper::getInstance()->findById($params['studentId']),
                ];
            });
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        /** @var IConfigLoader $config */
        $config = $injector->get('config');
        $injector->set(
            'tassportal:sso',
            $injector->lazyNew(
                SSO::class,
                [
                    'configuration' => [
                        'token_key' => $config->get('external_tass_sso_token_key'),
                        'cmpy_code' => $config->get('external_tass_company_code'),
                    ],
                    'server'        => $config->get('external_tass_sso_server'),
                    'ssoStrategy'   => $config->get('external_tass_sso_token_generation_strategy') ?: SSO::SSO_STRATEGY_DEFAULT,
                ]
            )
        );
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'sso'        => $injector->lazyGet('tassportal:sso'),
                    'portalHost' => $config->get('external_staff_portal'),
                    'appCodes'   => [
                        'parentLounge' => $config->get('external_tass_app_code_parent_lounge'),
                        'tassWeb'      => $config->get('external_tass_app_code_tassweb'),
                        'teacherKiosk' => $config->get('external_tass_app_code_teacherkiosk'),
                        'webBook'      => $config->get('external_tass_app_code_webbook'),
                        'studentCafe'  => $config->get('external_tass_app_code_studentcafe'),
                    ]
                ]
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new IsLoggedIn());
    }
}
