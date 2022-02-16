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
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Filter;
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
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('filters.factoryFactory'),
            $this->injector->get('l10n'),
            $this->getMapperFactory()->get(TermMapper::class),
            $this->getMapperFactory()->get(EventTypeMapper::class),
            $this->getMapperFactory()->get(Filter\Mapper::class),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
