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
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Schoolbox\Calendar;
use Schoolbox\CampusMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Item\Mapper as ItemMapper;

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
        $this->addRoutePreloadForBasename(
            $matcher,
            '(?:edit|update|approve|reject|setPending|cancel)',
            function($params) {
                return array(
                    'item' => Mapper::getInstance()->findById($params['id']),
                );
            }
        );
        // Add the resource item to params, if item ID is in the route.
        $this->addRoutePreloadForBasename(
            $matcher,
            '(?:book|createBooking|bookingCalendar)',
            function($params) {
                return array(
                    'item' => ItemMapper::getInstance()->findById($params['id']),
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        // Base management rule:
        // Any superuser or user with moderate access to resource booking
        // Resource management rule:
        // Base management rule, or has moderate perms on the item.
        // Create booking rule:
        // is staff member, and has booking rights on the item.


        // Approve/reject booking:
        // only moderators can do this.
        $baseManageRule = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasServicePermission(UserAccess::SERVICE_RESOURCE_BOOKING, UserAccess::ACCESS_FULL)
        );
        $isItemModeratorRule = new Auth\CanModerateBooking();
        $isItemModeratorRule->setMapper(Mapper::getInstance());
        $baseManageRule->addRule($isItemModeratorRule);

        // Create booking:
        // This depends on the the item - managed items can only be booked by
        // moderators, otherwise they may be booked by any staff member.
        $canBookRule = new Auth\CanBookItem();
        $canBookRule->setMapper(ItemMapper::getInstance());
        $bookingCreateRule = new Rule\Any($baseManageRule, $canBookRule);

        // Cancel/edit rule:
        // booking can be cancelled by a moderator, or the owner of the booking.
        $isOwnerRule = new Auth\UserOwnsBooking();
        $isOwnerRule->setMapper(Mapper::getInstance());
        $bookingEditRule = new Rule\Any($baseManageRule, $isOwnerRule);

        $this
            ->addRoutePermissionForBasename($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermissionForBasename($authorizer, '(?:book|createBooking)', $bookingCreateRule)
            ->addRoutePermissionForBasename($authorizer, '(?:approve|reject|setPending)', $baseManageRule)
            ->addRoutePermissionForBasename($authorizer, '(?:cancel|edit|update:post)', $bookingEditRule)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePathForBasename($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->setters[Controller::class]['setEventMapper']
            = $injector->lazyNew(Calendar\EventMapper::class);
        $injector->params[Calendar\EventRecurrenceMapper::class] = array(
            'eventMapper' => $injector->lazyNew(Calendar\EventMapper::class)
        );
        $injector->setters[Controller::class]['setEventRecurrenceMapper']
            = $injector->lazyNew(Calendar\EventRecurrenceMapper::class);
        $injector->setters[Controller::class]['setBookingMapper']
            = $injector->lazyNew(Mapper::class);
        $injector->setters[Controller::class]['setResourceMapper']
            = $injector->lazyNew(ItemMapper::class);
        $injector->setters[Controller::class]['setExternalTimetableHandler']
            = $injector->lazyNew('External_Timetable');
        $injector->setters[Controller::class]['setCampusMapper']
            = $injector->lazyNew(CampusMapper::class);
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
        );
    }
}
