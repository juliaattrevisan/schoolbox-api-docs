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
        // TODO this will need to become getEntities
        // Composites are designed to contain multiple factory factories, each of which may filter a different entity
        // TODO: Implement getEntity() method.
    }


    /**
     * @param string $filterDefinitionClass
     * @return IFactory
     */
    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory
    {
        if (!class_exists($filterDefinitionClass)) {
            throw new InvalidArgumentException('No filter definition found for class: ' . $filterDefinitionClass);
        }
        foreach ($this->factoryFactories as $factoryFactory) {
            try {
                return $factoryFactory->buildFilterFactoryForFilter($filterDefinitionClass);
            } catch (InvalidArgumentException $e) {
                // This factory factory doesn't build this filter: that is fine
                continue;
            }
        }
        throw new InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }


    /**
     * @return Codec
     */
    public function buildCodec(): Codec
    {
        // Do I need a composite codec?
        // TODO: Implement buildCodec() method.
    }


    /**
     * @param string $page
     * @return GenericCollection
     */
    public function buildEnabledKeyConverters($page): GenericCollection
    {
        $keyConverters = new GenericCollection(IKeyConverter::class);
        foreach ($this->factoryFactories as $factoryFactory) {
            $keyConverters = $keyConverters->union($factoryFactory->buildEnabledKeyConverters($page));
        }
        return $keyConverters;
    }


    /**
     * @param Collection $selectionsPerFilter
     * @param User       $user
     * @param string     $page
     * @return array
     */
    public function convertSelectionsToFilters(Collection $selectionsPerFilter, User $user, string $page): array
    {
        $filters = [];
        foreach ($this->factoryFactories as $factoryFactory) {
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $filters = array_replace(
                $filters,
                $factoryFactory->convertSelectionsToFilters($selectionsPerFilter, $user, $page)
            );
        }
        return $filters;
    }


    /**
     * @param string $page
     * @param bool   $enabledOnly
     * @return GenericCollection
     */
    public function buildCurrentState($page, $enabledOnly): GenericCollection
    {
        $currentState = new GenericCollection(IDefinition::class);
        foreach ($this->factoryFactories as $factoryFactory) {
            $currentState = $currentState->union($factoryFactory->buildCurrentState($page, $enabledOnly));
        }
        return $currentState;
    }


    /**
     * @param User      $user
     * @param string    $page
     * @param string    $route
     * @param User|null $viewee
     * @param array     $routeArgs
     * @param array     $request
     * @return Panel
     */
    public function buildPanel(User $user, string $page, string $route, ?User $viewee, array $routeArgs, array $request = []): Panel
    {
        // Do I need a composite panel?
        // TODO: Implement buildPanel() method.
    }
}
