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
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ResourceBooking\Item\Mapper as ItemMapper;

class Module extends BaseModule
{

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.
        $router->addGet('resourceBooking.book', $basePath.'/bookItem/{id}');
        $router->addGet('resourceBooking.edit', $basePath.'/{id}/edit');
        $router->addPost('resourceBooking.createBooking', $basePath.'/bookItem/{id}')
               ->addValues(array('action' => 'createBooking'));
        $router->addPost('resourceBooking.update:post', $basePath.'/{id}/update')
               ->addValues(array('action' => 'update'));
        $router->add('resourceBooking.bookingCalendar', $basePath.'/itemCalendar/{id}');

        // Approving or rejecting a single booking
        $router->add('resourceBooking.approve', $basePath.'/{id}/approve');
        $router->add('resourceBooking.reject', $basePath.'/{id}/reject');
        $router->add('resourceBooking.setPending', $basePath.'/{id}/setPending');
        $router->add('resourceBooking.cancel', $basePath.'/{id}/cancel');
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the booking item to params, if ID is in the route.
        $matcher->add(
            '/^resourceBooking\.(?:edit|update|approve|reject|setPending|cancel)/',
            function($params) {
                return array(
                    'item' => Mapper::getInstance()->findById($params['id']),
                );
            }
        );
        // Add the resource item to params, if item ID is in the route.
        $matcher->add(
            '/^resourceBooking\.(?:book|createBooking|bookingCalendar)/',
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

        $authorizer
            ->addRule('/^resourceBooking(?:$|\.)/', new Rule\IsLoggedIn())
            ->addRule('/^resourceBooking\.(?:book|createBooking)$/', $bookingCreateRule)
            ->addRule(
                '/^resourceBooking\.(?:approve|reject|setPending)$/',
                $baseManageRule
            )
            ->addRule('/^resourceBooking\.(?:cancel|edit|update:post)$/', $bookingEditRule)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/templates', 'resourceBooking');
        $templateLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->setters['Schoolbox\\ResourceBooking\\Booking\\Controller']['setEventMapper']
            = $injector->lazyNew('Schoolbox\\Calendar\\EventMapper');
        $injector->params['Schoolbox\\Calendar\\EventRecurrenceMapper'] = array(
            'eventMapper' => $injector->lazyNew('Schoolbox\\Calendar\\EventMapper')
        );
        $injector->setters['Schoolbox\\ResourceBooking\\Booking\\Controller']['setEventRecurrenceMapper']
            = $injector->lazyNew('Schoolbox\\Calendar\\EventRecurrenceMapper');
        $injector->setters['Schoolbox\\ResourceBooking\\Booking\\Controller']['setBookingMapper']
            = $injector->lazyNew('Schoolbox\\ResourceBooking\\Booking\\Mapper');
        $injector->setters['Schoolbox\\ResourceBooking\\Booking\\Controller']['setResourceMapper']
            = $injector->lazyNew('Schoolbox\\ResourceBooking\\Item\\Mapper');
        $injector->setters['Schoolbox\\ResourceBooking\\Booking\\Controller']['setExternalTimetableHandler']
            = $injector->lazyNew('External_Timetable');
        $injector->setters['Schoolbox\\ResourceBooking\\Booking\\Controller']['setCampusMapper']
            = $injector->lazyNew(\Schoolbox\CampusMapper::class);
        $injector->set(
            'controller:resourceBooking',
            $injector->lazyNew('Schoolbox\\ResourceBooking\\Booking\\Controller')
        );
    }
}
