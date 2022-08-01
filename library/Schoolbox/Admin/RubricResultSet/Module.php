<?php

/**
 * Rubrics Mapper
 *
 * @author    Tom McDonnell <tom.mcdonnell@alaress.com.au>
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\RubricResultSet;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'adminRubricResultSet';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $perms = new Rule\IsSuperuser();

        $this->register('GET', '/create', 'getCreate', $perms);
        $this->register('POST', '/create', 'postCreate', $perms);
        $this->register('GET', '/{resultSetId}/update', 'getUpdate', $perms);
        $this->register('POST', '/{resultSetId}/update', 'postUpdate', $perms);
        $this->register('POST', '/{resultSetId}/delete', 'postDelete', $perms);
        $this->register('GET', '', 'getList', $perms);
    }

    /**
     * Build the controller for this module.
     *
     * Dependencies can and should be injected into the controller's
     * constructor:
     * * objects specific to this controller may simply be constructed here
     * * mappers may be retrieved with
     *   `$this->getMapperFactory()->get(Mapper::class)`
     * * previously configured services may be extracted from the DI container
     *   with `$this->injector->get($serviceName)`
     *
     * @return Base
     */
    public function buildController(): Base
    {
        return new Controller($this->getMapperFactory()->get(Mapper::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
