<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Folder\Search;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
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
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $mapperFactory = $this->getMapperFactory();
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'folderMapper' => $mapperFactory->getLazy(Mapper::class),
                'access'       => $mapperFactory->getLazy(Access::class),
            ])
        );
    }
}
