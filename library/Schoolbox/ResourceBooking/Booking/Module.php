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
use Aura\Router\Map;
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
    public function initRoutes(string $basePath)
    {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.
        $this->addGetRoute('book', $basePath, '/bookItem/{id}');
        $this->addGetRoute('edit', $basePath, '/{id}/edit');
        $this->addPostRoute('createBooking', $basePath, '/bookItem/{id}')
             ->attributes(['action' => 'createBooking']);
        $this->addPostRoute('update:post', $basePath, '/{id}/update')
             ->attributes(['action' => 'update']);
        $this->addRoute('bookingCalendar', $basePath, '/itemCalendar/{id}');
        $this->addPostRoute('addBookingsToEvent', $basePath, '/addBookingsToEvent');

        // Approving or rejecting a single booking
        $this->addRoute('approve', $basePath, '/{id}/approve');
        $this->addRoute('reject', $basePath, '/{id}/reject');
        $this->addRoute('setPending', $basePath, '/{id}/setPending');
        $this->addRoute('cancel', $basePath, '/{id}/cancel');

        // Clash booking, approve the new booking and reject the old one
        $this->addRoute('clashApprove', $basePath, '/{old}/{new}/clashApprove');
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

        // add the booking items to params for clash approve
        $this->addRoutePreload(
            $matcher,
            '(?:clashApprove)',
            function ($params) {
                try {
                    return [
                        'oldBooking' => $this->getMapper()->findById($params['old']),
                        'item' => $this->getMapper()->findById($params['new'])
                    ];
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
        $isItemModeratorRule->setItemMapper($this->getItemMapper());
        $manageBookingRule = new Rule\Any($baseManageRule, $isItemModeratorRule);

        // Create booking:
        // This depends on the item - managed items can only be booked by
        // moderators, otherwise they may be booked by any staff member.
        $canBookRule = new Auth\CanBookItem();
        $canBookRule->setMapper($this->getItemMapper());
        $bookingCreateRule = new Rule\Any($baseManageRule, $canBookRule);

        // Cancel/edit rule:
        // booking can be cancelled by a moderator, or the owner of the booking.
        $isOwnerRule = new Auth\UserOwnsBooking();
        $isOwnerRule->setMapper($this->getMapper());
        $bookingEditRule = new Rule\Any($baseManageRule, $isOwnerRule, $isItemModeratorRule);

        // Add bookings rule:
        // This is different from the $bookingCreateRule because 'add bookings' adds multiple bookings, for multiple
        // resources.  Because this rule concerns multiple resources, the $canBookRule is unsuitable (the $canBookRule
        // is only suitable if the id of the booking item (the resource) can be read from the path.
        // The permission check for each resource must instead be done in the controller.
        $addBookingsToEventRule = new Rule\IsLoggedIn(); // TODO: Fix this rule (test with Jack Coles).

        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, ['book', 'createBooking'], $bookingCreateRule)
            ->addRoutePermission($authorizer, ['approve', 'reject', 'setPending', 'clashApprove'], $manageBookingRule)
            ->addRoutePermission($authorizer, ['cancel', 'edit', 'update:post'], $bookingEditRule)
            ->addRoutePermission($authorizer, ['addBookingsToEvent'], $addBookingsToEventRule);
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
            ->setEventRecurrenceMapper(new Calendar\EventRecurrenceMapper($this->getMapperFactory()->get(Calendar\EventMapper::class)))
            ->setBookingMapper($this->getMapperFactory()->get(Mapper::class))
            ->setResourceMapper($this->getMapperFactory()->get(ItemMapper::class))
            ->setExternalTimetableHandler($this->injector->get('externalTimetable'))
            ->setCampusMapper($this->getMapperFactory()->get(CampusMapper::class))
            ->setStatusMapper($this->getMapperFactory()->get(StatusMapper::class))
            ->setTermMapper($this->getMapperFactory()->get(TermMapper::class))
            ->setExternalEventMapper($this->getMapperFactory()->get(\Schoolbox\External\Event\Mapper::class));
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
