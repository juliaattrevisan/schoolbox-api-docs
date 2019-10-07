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
use Alaress\Authorizer\Rule\HasServicePermission;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Authorizer\Rule\LazyCallback;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\Relation\Campus;
use Schoolbox\Calendar\Relation\Category;
use Schoolbox\Calendar\Relation\Role;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\RoleMapper;

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
                    new HasServicePermission(UserAccess::SERVICE_CALENDAR, UserAccess::ACCESS_FULL),
                    new Callback(function (array $params) {
                        /** @var Event $event */
                        $event = $params['event'];
                        $loggedInUser   = $this->getAuthorizer()->getUser();

                        /**
                         * Personal Event: owner, parent of owner and staff can see event
                         * Group: READ+ members of group can see event
                         * Campus: user can see event if in campus and roles
                         */
                        if ($event->isPersonalLevel()) {
                            $isStaff        = $loggedInUser->getRole()->isStaff();
                            $isOwner        = $event->getAuthor()->getId() === $loggedInUser->getId();
                            $isParentOfUser = $loggedInUser->isParentOf($event->getAuthor()->loadParents());

                            if ($isOwner || $isStaff || $isParentOfUser) {
                                return true;
                            }
                        } elseif ($event->isGroupLevel()) {
                            /** @var Category $item */
                            foreach ($event->getCategoryCollection()->getAll() as $item) {
                                if (Access::getInstance()->getFolderAccess($loggedInUser, $item->getCategoryId()) >= UserAccess::ACCESS_READ) {
                                    return true;
                                }
                            }
                        } elseif ($event->isCampusLevel()) {
                            $inRole = false;
                            $inCampus = false;

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

        $this->register('GET', '/{eventId:\d+}', 'getEvent', $perms, $preloadEvent);
    }

    /**
     * @inheritDoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
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
     * @return array|null
     * @throws NotFound
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function preloadEvent(array $params): ?array
    {
        try {
            return ['event' => $this->getEventMapper()->findById($params['eventId'])];
        } catch (MapperException $e) {
            throw new NotFound('Event not found');
        }
    }

    /**
     * @return EventMapper
     */
    private function getEventMapper(): EventMapper
    {
        return $this->getMapperFactory()->get(EventMapper::class);
    }

    /**
     * @inheritDoc
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'roleMapper' => $this->getMapperFactory()->getLazy(RoleMapper::class),
            'folderMapper' => $this->getMapperFactory()->getLazy(FolderMapper::class),
        ];
    }
}
