<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event\Attendance;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Authorizer\Rule\All;
use Alaress\Authorizer\Rule\Any;
use Alaress\Authorizer\Rule\Callback;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Authorizer\Rule\LazyCallback;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventRecurrenceMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'calendarEventAttendance';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        /* Anyone can see the form
         */
        $getPerms = new Rule\All(
            new Rule\IsLoggedIn()
        );

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


        $preloadEvent = function ($params) {
            return $this->preloadEvent($params);
        };

        $this->register('GET', '/{eventId}', 'getCurrent', $getPerms, $preloadEvent);
        $this->register('POST', '/{eventId}/create', 'postCreate', $modifyPerms, $preloadEvent);
        $this->register('POST', '/{eventId}/delete', 'postDelete', $modifyPerms, $preloadEvent);
        $this->register('POST', '/{eventId}/accept', 'postAccept', $getPerms, $preloadEvent);
        $this->register('POST', '/{eventId}/decline', 'postDecline', $getPerms, $preloadEvent);
    }


    /**
     * Preload an Event instance
     *
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadEvent(array $params): array
    {
        try {
            $event = $this->getMapperFactory()->get(EventMapper::class)->findById($params['eventId']);
        } catch (MapperException $e) {
            throw new NotFound();
        }

        return [
            'event' => $event,
        ];
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'errorMapper'           => $this->getMapperFactory()->getLazy(ErrorMapper::class),
            'eventMapper'           => $this->getMapperFactory()->getLazy(EventMapper::class),
            'attendanceMapper'      => $this->getMapperFactory()->getLazy(Mapper::class),
            'eventRecurrenceMapper' => $injector->lazyNew(EventRecurrenceMapper::class, [
                'eventMapper' => $this->getMapperFactory()->getLazy(EventMapper::class),
            ]),
        ];
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
