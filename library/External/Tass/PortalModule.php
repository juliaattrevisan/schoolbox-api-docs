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
    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $router->add('tassportal.parentLounge', $basePath.'/parentlounge');
        $router->add('tassportal.tassWeb', $basePath.'/tassweb');
        $router->add('tassportal.teacherKiosk', $basePath.'/teacherkiosk');
        $router->add('tassportal.webBook', $basePath.'/webbook');
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
        $authorizer->addRule('/^tassportal(?:$|\.)/', new IsLoggedIn());
    }
}
