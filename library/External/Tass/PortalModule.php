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
        $injector->set(
            'controller:tassportal',
            $injector->lazyNew(External_Tass_PortalController::class)
        );
    }
}
