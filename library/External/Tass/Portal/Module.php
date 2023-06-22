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
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Aura\Router\Map;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\UserMapper;

class Module extends \Alaress\Module\Module
{
    protected $basename = 'tassportal';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Map $routeMap, string $basePath)
    {
        $this->addRoute($routeMap, 'parentLounge', $basePath, '/parentlounge');
        $this->addRoute($routeMap, 'tassWeb', $basePath, '/tassweb');
        $this->addRoute($routeMap, 'teacherKiosk', $basePath, '/teacherkiosk');
        $this->addRoute($routeMap, 'studentProfile', $basePath, '/studentProfile/{studentId}');
        $this->addRoute($routeMap, 'webBook', $basePath, '/webbook');
        $this->addRoute($routeMap, 'studentCafe', $basePath, '/studentcafe');
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $config = $this->getConfig();
        return new Controller(
            new SSO(
                [
                    'token_key' => $config->get('external_tass_sso_token_key'),
                    'cmpy_code' => $config->get('external_tass_company_code'),
                ],
                $config->get('external_tass_sso_server'),
                $config->get('external_tass_sso_token_generation_strategy') ?: SSO::SSO_STRATEGY_DEFAULT,
            ),
            $config->get('external_staff_portal'),
            [
                'parentLounge' => $config->get('external_tass_app_code_parent_lounge'),
                'tassWeb'      => $config->get('external_tass_app_code_tassweb'),
                'teacherKiosk' => $config->get('external_tass_app_code_teacherkiosk'),
                'webBook'      => $config->get('external_tass_app_code_webbook'),
                'studentCafe'  => $config->get('external_tass_app_code_studentcafe'),
            ],
            $this->getMapperFactory()->get(ErrorMapper::class)
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
