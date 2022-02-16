<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Search;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'folderSearch';


    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $this->register('GET', '', 'getFolderSearch', new Rule\IsLoggedIn());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(Access::class),
        );
    }
}
