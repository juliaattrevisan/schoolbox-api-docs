<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Filter;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Role;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningAssessmentCalendar';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        // Only staff can visit the page and request a report
        $routePerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRole(Role::STAFF)
        );

        $this->register('GET', '', 'getReport', $routePerms);
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
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'filterFactoryFactory' => $injector->lazyGet('filters.factoryFactory'),
            'rosettaStone'         => $injector->lazyGet('l10n'),
            'termMapper'           => $this->getMapperFactory()->getLazy(TermMapper::class),
            'eventTypeMapper'      => $this->getMapperFactory()->getLazy(EventTypeMapper::class),
        ];
    }
}
