<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 */

namespace Schoolbox\Search\Header;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Module extends BaseModule
{
    protected $basename = 'headersearch';

    /**
     * {@inheritdoc}
     */
    protected function registerRoutes(): void
    {
        $loggedIn = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn()
            );
        });

        $this->register('GET', '', 'search', $loggedIn);
        $this->register('GET', '/classes', 'searchClasses', $loggedIn);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(Access::class), // Not a mapper, but has getInstance, so does work.
        );
    }
}
