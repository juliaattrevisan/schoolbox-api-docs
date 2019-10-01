<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Alaress\Authorizer\Rule\All;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Authorizer\Rule\LazyCallback;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception\NotFound;
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
        $perms = new LazyCallback(function () {
            return new All(
                new IsLoggedIn()
            );
        });

        $preloadEvent = function ($params) {
            return $this->preloadEvent($params);
        };

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
            return ['event' => $this->getEventMapper()->findById($params['eventId']) ];
        }
        catch (MapperException $e) {
            throw new NotFound(ErrorMapper::ERROR_EVENT_NOT_FOUND);
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
        ];
    }
}
