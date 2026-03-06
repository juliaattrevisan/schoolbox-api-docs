<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\All;
use Alaress\Authorizer\Rule\Any;
use Alaress\Authorizer\Rule\Callback;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Authorizer\Rule\LazyCallback;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\External\Event\Mapper as ExternalEventMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\ResourceBooking\Booking\Status\Mapper as BookingStatusMapper;
use Schoolbox\ResourceBooking\Item\Mapper as BookingItemMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\ResourceBooking\Booking\Mapper as ResourceBookingMapper;
use Schoolbox\ResourceBooking\Booking\Util\ResponseHelper as BookingResponseHelper;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;
use Schoolbox\Editor\Templates\Mapper as EditorTemplateMapper;
use Schoolbox\Admin\Editor\Templates\Category\Model as EditorTemplatesCategoryModel;

/**
 * Class Module
 *
 * @package Schoolbox\Calendar\Event
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'calendarEvent';

    /**
     * @inheritDoc
     */
    protected function registerRoutes(): void
    {
        $preloadEvent = function ($params) {
            return $this->preloadEvent($params);
        };

        $perms = new LazyCallback(function () {
            return new All(
                new IsLoggedIn(),
                new Any(
                    new IsSuperuser(),
                    new Event\Authorizer\CanViewEvent($this->getConfig())
                )
            );
        });

        /* Permissions for creating an event
         */
        $createPerms = new LazyCallback(function () {
            return new All(
                new IsLoggedIn()
            );
        });

        /* Permissions for modifying an event
         */
        $modifyPerms = new LazyCallback(function () {
            return new All(
                new IsLoggedIn(),
                new Any(
                    new IsSuperuser(),
                    new Callback(function (array $params) {
                        try {
                            $event = $this->getEventMapper()->findById($params['eventId']);
                            //hydrate parents for further permission checks
                            $this->getRelationshipHydrator()->loadParents([$event->getOwner()]);
                            if ((new Event\Access(Access::getInstance()))->checkExisting($this->getAuthorizer()->getUser(), $event)) {
                                return true;
                            }
                        } catch (Exception $e) {
                            // noop
                        }

                        throw new Unauthorized();
                    })
                )
            );
        });

        $this->register('GET', '/{eventId:\d+}', 'getEvent', $perms, $preloadEvent);
        $this->register('GET', '/{eventId:\d+}/modify', 'getModify', $modifyPerms, $preloadEvent);
        $this->register('POST', '/{eventId:\d+}/move', 'postMove', $modifyPerms, $preloadEvent);
        $this->register('GET', '/create', 'getCreate', $createPerms);
        $this->register('POST', '/create', 'postCreate', $createPerms);
        $this->register('POST', '/createForForm', 'postCreateForForm', $createPerms);
        $this->register('POST', '/{eventId:\d+}/delete', 'postDelete', $modifyPerms, $preloadEvent);
        $this->register('POST', '/{eventId:\d+}/modify', 'postModify', $modifyPerms, $preloadEvent);
        $this->register('POST', '/{eventId:\d+}/modifyForForm', 'postModifyForForm', $modifyPerms, $preloadEvent);
        $this->register('POST', '/{eventId:\d+}/addBookings', 'addBookings', $modifyPerms, $preloadEvent);
        $this->register('POST', '/{eventId:\d+}/editBooking/{bookingId}', 'editBooking', $modifyPerms, $preloadEvent);
        $this->register('DELETE', '/{eventId:\d+}/deleteBooking/{bookingId}', 'deleteBooking', $modifyPerms, $preloadEvent);
        $this->register('POST', '/{eventId:\d+}/addToCalendar', 'addToCalendar', $modifyPerms, $preloadEvent);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(EventMapper::class),
            $this->getMapperFactory()->get(EventTypeMapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            $this->getMapperFactory()->get(Attendance\Mapper::class),
            $this->getMapperFactory()->get(Access::class), // Not a mapper, but has getInstance, so does work.
            new Validator(new \Alaress_Date_Locale_Config()),
            new EditorTemplateHelper(
                $this->getMapperFactory()->get(EditorTemplateMapper::class),
                EditorTemplatesCategoryModel::CALENDAR_EVENTS,
                $this->injector->get('user')
            ),
            $this->getMapperFactory()->get(BookingItemMapper::class),
            $this->getMapperFactory()->get(BookingStatusMapper::class),
            $this->getMapperFactory()->get(ResourceBookingMapper::class),
            $this->getMapperFactory()->get(TermMapper::class),
            $this->getMapperFactory()->get(ExternalEventMapper::class),
            $this->injector->get('externalTimetable'),
            new BookingResponseHelper,
        );
    }

    /**
     * @inheritDoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @param array $params
     * @return array
     * @throws Exception
     * @throws Mapper\Exception\Deleted
     * @throws InvalidArgumentException
     */
    private function preloadEvent(array $params): array
    {
        $event = $this->getEventMapper()->findById($params['eventId']);
        //hydrate parents for further permission checks
        $this->getRelationshipHydrator()->loadParents([$event->getOwner()]);

        return ['event' => $event];
    }

    /**
     * @return EventMapper
     * @throws InvalidArgumentException
     */
    private function getEventMapper(): EventMapper
    {
        return $this->getMapperFactory()->get(EventMapper::class);
    }

    /**
     * Quick call to build the external user relationship hydrator
     *
     * @return \Schoolbox\External\User
     */
    protected function getRelationshipHydrator()
    {
        return new \Schoolbox\External\User(
            $this->getConfig()->get('external_type'),
            new \External_Class(),
            new \External_User(),
            $this->getMapperFactory()->get(UserMapper::class)
        );
    }
}
