<?php

/**
 * Module definition for the booking part of the resource booking module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Booking;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Calendar;
use Schoolbox\CampusMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Item\Mapper as ItemMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as StatusMapper;
use Schoolbox\Application\Exception\NotFound;

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
                try {
                    return ['item' => $this->getMapper()->findById($params['id'])];
                } catch (\Schoolbox\Mapper\Exception $e) {
                    throw new NotFound();
                }
            }
        );
        // Add the resource item to params, if item ID is in the route.
        $this->addRoutePreload(
            $matcher,
            '(?:book|createBooking|bookingCalendar)',
            function ($params) {
                try {
                    return ['item' => $this->getItemMapper()->findById($params['id'])];
                } catch (\Schoolbox\Mapper\Exception $e) {
                    throw new NotFound();
                }
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return (new Controller())
            ->setEventMapper($this->getMapperFactory()->get(Calendar\EventMapper::class))
            ->setEventRecurrenceMapper($this->getMapperFactory()->get(Calendar\EventMapper::class))
            ->setBookingMapper($this->getMapperFactory()->get(Mapper::class))
            ->setResourceMapper($this->getMapperFactory()->get(ItemMapper::class))
            ->setExternalTimetableHandler($this->injector->get('externalTimetable'))
            ->setCampusMapper($this->getMapperFactory()->get(CampusMapper::class))
            ->setStatusMapper($this->getMapperFactory()->get(StatusMapper::class))
            ->setTermMapper($this->getMapperFactory()->get(TermMapper::class));
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
