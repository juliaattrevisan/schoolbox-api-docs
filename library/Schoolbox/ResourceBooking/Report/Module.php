<?php
/**
 * Module definition for the resource booking report.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\ResourceBooking\Report;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\ResourceBooking\Booking;

class Module extends BaseModule
{

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.
        $router->addGet('resourceBookingReport.index', $basePath);
        $router->addGet('resourceBookingReport.download', $basePath.'/download');
    }

    /**
     * Only moderators may view the report.
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $authorizer
            ->addRule('/^resourceBookingReport\./', new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(
                        UserAccess::SERVICE_RESOURCE_BOOKING,
                        UserAccess::ACCESS_FULL
                    )
                )
            ))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/templates', 'resourceBookingReport');
        $templateLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->params[Controller::class]['bookingMapper'] = $injector->lazyNew(Booking\Mapper::class);
        $injector->params[Controller::class]['itemMapper']    = $injector->lazyNew(Item\Mapper::class);
        $injector->set('controller:resourceBookingReport', $injector->lazyNew(Controller::class));
    }
}
