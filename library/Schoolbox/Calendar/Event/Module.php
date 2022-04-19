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
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Calendar\Relation\Role;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

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
                    new Callback(function (array $params) {
                        /** @var Event $event */
                        $event = $params['event'];

                        $loggedInUser = $this->getAuthorizer()->getUser();

                        /**
                         * Personal Event: owner, parent of owner, staff, or invited guest, can see event
                         * Group: READ+ members of group can see event
                         * Campus: user can see event if in campus and roles
                         */
                        if ($event->isPersonalLevel()) {
                            $isStaff        = $loggedInUser->getRole()->isStaff();
                            $isOwner        = $event->getOwner()->getId() === $loggedInUser->getId();
                            $isParentOfUser = $loggedInUser->isParentOf($event->getAuthor()->loadParents());

                            /* I suppose if you're on the invited list,
                             * you should be able to see it
                             */
                            $isAttendee = $this->getMapperFactory()->get(Attendance\Mapper::class)->countByColumnList([
                                'event_id' => $event->getId(),
                                'user_id' => $loggedInUser->getId()
                            ]);

                            if ($isOwner || $isStaff || $isParentOfUser || $isAttendee) {
                                return true;
                            }
                        } elseif ($event->isGroupLevel()) {
                            /** @var Folder $folder */
                            foreach ($event->getFolderCollection()->getAll() as $folder) {
                                if (Access::getInstance()->getFolderAccess($loggedInUser, $folder->getId()) >= UserAccess::ACCESS_READ) {
                                    return true;
                                }
                            }
                        } elseif ($event->isCampusLevel()) {
                            $inRole = false;
                            $inCampus = false;

                            foreach ($loggedInUser->getAccessList()->filterBy('service', UserAccess::SERVICE_CALENDAR) as $access) {
                                if ($access->getAccessLevel() >= UserAccess::ACCESS_READ) {
                                    /** @var Campus $campus */
                                    foreach ($event->getCampusCollection()->getAll() as $campus) {
                                        if ($campus->getId() === $access->getServiceIdentifier()) {
                                            return true;
                                        }
                                    }
                                }
                            }

                            /** @var Campus $campus */
                            foreach ($event->getCampusCollection() as $campus) {
                                if ($loggedInUser->isInCampus($campus)) {
                                    $inCampus = true;
                                    break;
                                }
                            }

                            if ($event->getRoleCollection()->getBy('roleId', $loggedInUser->getRole()->getId()) instanceof Role) {
                                $inRole = true;
                            }

                            if ($inCampus && $inRole) {
                                return true;
                            }
                        }

                        throw new Unauthorized();
                    })
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
        $this->register('POST', '/{eventId:\d+}/modify', 'postModify', $modifyPerms, $preloadEvent);
        $this->register('POST', '/{eventId:\d+}/move', 'postMove', $modifyPerms, $preloadEvent);
        $this->register('GET', '/create', 'getCreate', $createPerms);
        $this->register('POST', '/create', 'postCreate', $createPerms);
        $this->register('POST', '/{eventId:\d+}/delete', 'postDelete', $modifyPerms, $preloadEvent);
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
        return ['event' => $this->getEventMapper()->findById($params['eventId'])];
    }

    /**
     * @return EventMapper
     * @throws InvalidArgumentException
     */
    private function getEventMapper(): EventMapper
    {
        return $this->getMapperFactory()->get(EventMapper::class);
    }
}
