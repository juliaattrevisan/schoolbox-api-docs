<?php

/**
 * Module definition for the resource booking report.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Report;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Calendar_Api_Composite;
use External_Timetable;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;
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
        $this->addRoutePermission(
            $authorizer,
            '.*',
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            )
        );
    }

    /**
     * {@inheritdoc}
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
            $this->getMapperFactory()->get(Item\Mapper::class),
            $this->getMapperFactory()->get(Category\Mapper::class),
            new Calendar_Api_Composite(),
            new CalendarApiFactory(
                $this->injector->get('user'),
                new External_Timetable(),
            ),
        );
    }
}
