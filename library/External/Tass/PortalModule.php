<?php
/**
 * TASS portal module
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Aura\Di\ContainerInterface;
use Aura\Router\Router;

class External_Tass_PortalModule extends \Alaress\Module\Module
{
    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath) {
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
        // Add a External_Tass_SSO object
        $injector->set(
            'tassportal:sso',
            $injector->lazyNew(
                External_Tass_SSO::class,
                [
                    'configuration' => [
                        'token_key' => 'L@g00n1917',
                    ],
                    'server' => 'https://tassportal.cathedral.qld.edu.au/tassweb/external/sso_gateway.cfm',
                ]
            )
        );
        $injector->set(
            'controller:tassportal',
            $injector->lazyNew(
                External_Tass_PortalController::class,
                [
                    'sso'        => $injector->lazyGet('tassportal:sso'),
                    'portalHost' => 'https://tassportal.cathedral.qld.edu.au/',
                ]
            )
        );
    }
}
