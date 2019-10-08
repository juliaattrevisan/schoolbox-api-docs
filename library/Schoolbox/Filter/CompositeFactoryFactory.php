<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter;

use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Class CompositeFactoryFactory
 */
class CompositeFactoryFactory implements IFactoryFactory
{
    /**
     * @var IFactoryFactory[]
     */
    private $factoryFactories;


    /**
     * Constructor
     *
     * @param IFactoryFactory ...$factoryFactories
     */
    public function __construct(IFactoryFactory... $factoryFactories)
    {
        $this->factoryFactories = $factoryFactories;
    }


    public static function getEntity(): string
    {
        // TODO: Implement getEntity() method.
    }

    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory
    {
        // TODO: Implement buildFilterFactoryForFilter() method.
    }

    public function buildCodec(): Codec
    {
        // TODO: Implement buildCodec() method.
    }

    public function buildEnabledKeyConverters($page): GenericCollection
    {
        // TODO: Implement buildEnabledKeyConverters() method.
    }

    public function convertSelectionsToFilters(Collection $selectionsPerFilter, User $user, string $page): array
    {
        // TODO: Implement convertSelectionsToFilters() method.
    }

    public function buildCurrentState($page, $enabledOnly): GenericCollection
    {
        // TODO: Implement buildCurrentState() method.
    }

    public function buildPanel(User $user, string $page, string $route, ?User $viewee, array $routeArgs, array $request = []): Panel
    {
        // TODO: Implement buildPanel() method.
    }
}
