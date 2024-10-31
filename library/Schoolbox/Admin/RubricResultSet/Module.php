<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\RubricResultSet;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Permission\Access\UserAccess;

/**
 * RubricResultSet module class.
 */
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
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL)
            )
        );

        $this->register('GET', '/create', 'getCreate', $perms);
        $this->register('POST', '/create', 'postCreate', $perms);
        $this->register('GET', '/{resultSetId}/update', 'getUpdate', $perms);
        $this->register('POST', '/{resultSetId}/update', 'postUpdate', $perms);
        $this->register('DELETE', '/{resultSetId}', 'deleteResultSet', $perms);
        $this->register('GET', '', 'getList', $perms);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller($this->getMapperFactory()->get(Mapper::class));
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
