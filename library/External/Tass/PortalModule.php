<?php
/**
 * TASS portal module
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;

class External_Tass_PortalModule extends \Alaress\Module\Module
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
        $this->addRoute($router, 'webBook', $basePath, '/webbook');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $config = $injector->get('config');
        $injector->set(
            'tassportal:sso',
            $injector->lazyNew(
                External_Tass_SSO::class,
                [
                    'configuration' => [
                        'token_key' => $config->get('external_tass_sso_token_key'),
                        'cmpy_code' => $config->get('external_tass_company_code'),
                    ],
                    'server' => $config->get('external_tass_sso_server'),
                ]
            )
        );
        $injector->set(
            'controller:tassportal',
            $injector->lazyNew(
                External_Tass_PortalController::class,
                [
                    'sso'        => $injector->lazyGet('tassportal:sso'),
                    'portalHost' => $config->get('external_staff_portal'),
                    'appCodes'   => [
                        'parentLounge' => $config->get('external_tass_app_code_parent_lounge'),
                        'tassWeb'      => $config->get('external_tass_app_code_tassweb'),
                        'teacherKiosk' => $config->get('external_tass_app_code_teacherkiosk'),
                        'webBook'      => $config->get('external_tass_app_code_webbook'),
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
        $this->addRoutePermissionForBasename($authorizer, '.*', new IsLoggedIn());
    }
}
