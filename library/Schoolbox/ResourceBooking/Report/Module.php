<?php

/**
 * Module definition for the resource booking report.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\ResourceBooking\Report;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Aura\Router\Map;
use Calendar_Api_Composite;
use External_Timetable;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\CampusMapper;
use Schoolbox\ResourceBooking\Booking\Mapper as BookingMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
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
    public function initRoutes(string $basePath)
    {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.
        $this->addGetRoute('index', $basePath, '');
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
        $eventMapper = $this->getMapperFactory()->get(\Schoolbox\External\Event\Mapper::class);

        return new Controller(
            $this->getMapperFactory()->get(Item\Mapper::class),
            $this->getMapperFactory()->get(Category\Mapper::class),
            new Calendar_Api_Composite(),
            new CalendarApiFactory(
                $this->injector->get('user'),
                new External_Timetable(),
                $eventMapper
            ),
            $this->getMapperFactory()->get(BookingMapper::class),
            $this->getMapperFactory()->get(StatusMapper::class),
            $this->getMapperFactory()->get(TermMapper::class),
            $this->getMapperFactory()->get(\External_Timetable::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $eventMapper,
        );
    }
}
