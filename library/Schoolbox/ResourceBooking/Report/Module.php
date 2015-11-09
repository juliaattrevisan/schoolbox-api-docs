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
use Calendar_Api_Composite;
use External_Timetable;
use Schoolbox\Role;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Category;
use Schoolbox\ResourceBooking\Item;

class Module extends BaseModule
{
    protected $basename = 'resourceBookingReport';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.
        $this->addGetRoute($router, 'index', $basePath, '');
        $this->addGetRoute($router, 'download', $basePath, '/download');
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
                    new Rule\HasRole(Role::STAFF)
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
        // Build a calendar API composite and an API factory
        $injector->params[CalendarApiFactory::class]['user']      = $injector->lazyGet('user');
        $injector->params[CalendarApiFactory::class]['timetable'] = $injector->lazyNew(External_Timetable::class);

        $injector->params[Controller::class]['itemMapper']     = $injector->lazyNew(Item\Mapper::class);
        $injector->params[Controller::class]['categoryMapper'] = $injector->lazyNew(Category\Mapper::class);
        $injector->params[Controller::class]['apiFactory']     = $injector->lazyNew(CalendarApiFactory::class);
        $injector->params[Controller::class]['api']            = $injector->lazyNew(Calendar_Api_Composite::class);
        $injector->set('controller:resourceBookingReport', $injector->lazyNew(Controller::class));
    }
}
