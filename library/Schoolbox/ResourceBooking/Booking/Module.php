<?php
/**
 * Module definition for the booking part of the resource booking module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Alaress\Twig\Loader\IChainLoader;
use Twig_Loader_Filesystem;
use Schoolbox\Calendar;
use Schoolbox\CampusMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Item\Mapper as ItemMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;

class Module extends BaseModule
{
    protected $basename = 'resourceBooking';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.
        $this->addGetRoute($router, 'book', $basePath, '/bookItem/{id}');
        $this->addGetRoute($router, 'edit', $basePath, '/{id}/edit');
        $this->addPostRoute($router, 'createBooking', $basePath, '/bookItem/{id}')
            ->addValues(array('action' => 'createBooking'));
        $this->addPostRoute($router, 'update:post', $basePath, '/{id}/update')
            ->addValues(array('action' => 'update'));
        $this->addRoute($router, 'bookingCalendar', $basePath, '/itemCalendar/{id}');

        // Approving or rejecting a single booking
        $this->addRoute($router, 'approve', $basePath, '/{id}/approve');
        $this->addRoute($router, 'reject', $basePath, '/{id}/reject');
        $this->addRoute($router, 'setPending', $basePath, '/{id}/setPending');
        $this->addRoute($router, 'cancel', $basePath, '/{id}/cancel');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the booking item to params, if ID is in the route.
        $this->addRoutePreload(
            $matcher,
            '(?:edit|update|approve|reject|setPending|cancel)',
            function ($params) {
                return array(
                    'item' => $this->getMapper()->findById($params['id']),
                );
            }
        );
        // Add the resource item to params, if item ID is in the route.
        $this->addRoutePreload(
            $matcher,
            '(?:book|createBooking|bookingCalendar)',
            function ($params) {
                return array(
                    'item' => $this->getItemMapper()->findById($params['id']),
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        // Base rules:
        // a superuser or RB moderator can do anything in this module
        $baseManageRule = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasServicePermission(UserAccess::SERVICE_RESOURCE_BOOKING, UserAccess::ACCESS_FULL)
        );

        // Approve/reject booking:
        // a moderator of a booked item can do this.
        $isItemModeratorRule = new Auth\CanModerateBooking();
        $isItemModeratorRule->setMapper($this->getMapper());
        $manageBookingRule = new Rule\Any($baseManageRule, $isItemModeratorRule);

        // Create booking:
        // This depends on the the item - managed items can only be booked by
        // moderators, otherwise they may be booked by any staff member.
        $canBookRule = new Auth\CanBookItem();
        $canBookRule->setMapper($this->getItemMapper());
        $bookingCreateRule = new Rule\Any($baseManageRule, $canBookRule);

        // Cancel/edit rule:
        // booking can be cancelled by a moderator, or the owner of the booking.
        $isOwnerRule = new Auth\UserOwnsBooking();
        $isOwnerRule->setMapper($this->getMapper());
        $bookingEditRule = new Rule\Any($baseManageRule, $isOwnerRule, $isItemModeratorRule);

        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, ['book', 'createBooking'], $bookingCreateRule)
            ->addRoutePermission($authorizer, ['approve', 'reject', 'setPending'], $manageBookingRule)
            ->addRoutePermission($authorizer, ['cancel', 'edit', 'update:post'], $bookingEditRule);
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->setters[Controller::class]['setEventMapper']
            = $this->getMapperFactory()->getLazy(Calendar\EventMapper::class);
        $injector->setters[Controller::class]['setEventRecurrenceMapper']
            = $injector->lazyNew(Calendar\EventRecurrenceMapper::class, [
            'eventMapper' => $this->getMapperFactory()->getLazy(Calendar\EventMapper::class)
        ]);
        $injector->setters[Controller::class]['setBookingMapper']
            = $this->getMapperFactory()->getLazy(Mapper::class);
        $injector->setters[Controller::class]['setResourceMapper']
            = $this->getMapperFactory()->getLazy(ItemMapper::class);
        $injector->setters[Controller::class]['setExternalTimetableHandler']
            = $injector->get('externalTimetable');
        $injector->setters[Controller::class]['setCampusMapper']
            = $this->getMapperFactory()->getLazy(CampusMapper::class);
        $injector->setters[Controller::class]['setStatusMapper']
            = $this->getMapperFactory()->getLazy(StatusMapper::class);
        $injector->setters[Controller::class]['setTermMapper']
            = $this->getMapperFactory()->getLazy(TermMapper::class);
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class)
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper
     */
    protected function getMapper()
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }

    /**
     * @return ItemMapper
     */
    protected function getItemMapper()
    {
        return $this->getMapperFactory()->get(ItemMapper::class);
    }
}
