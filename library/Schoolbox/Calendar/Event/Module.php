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
use Aura\Di\Container;
use Aura\Di\ContainerInterface;

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

        $this->register('GET', '/{eventId:\d+}', 'getEvent', $perms);
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
     * @inheritDoc
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'databaseInternal' => $injector->lazyGet('databaseInternal'),
        ];
    }
}
