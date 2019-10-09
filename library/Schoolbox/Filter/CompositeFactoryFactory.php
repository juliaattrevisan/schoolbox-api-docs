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
 * Composite factory factory: can contain multiple factory factories.
 * * `buildFilterFactoryForFilter` tries to use each factory factory to build
 *   the requested class
 * * other implemented methods aggregate the results from each factory factory
 *   into a single result
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


    /**
     * The composite's filter factory attempts to use each contained factory
     * factory to create the filter factory: one of the contained items should
     * be able to create what is requested.
     *
     * @inheritDoc
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
     * @return CompositeCodec
     */
    public function buildCodec(): ICodec
    {
        $codecs = [];
        foreach ($this->factoryFactories as $factoryFactory) {
            $codecs[] = $factoryFactory->buildCodec();
        }
        return new CompositeCodec(...$codecs);
    }


    /**
     * The composite's key converters are a union of the converters for all of
     * its contained factory factories.
     *
     * @inheritDoc
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
     * The composite's filters are a union of the filters for all of its
     * contained factory factories.
     *
     * @inheritDoc
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
     * The composite's current state is a union of the state of all of its
     * contained factory factories.
     *
     * @inheritDoc
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
     * The composite factory panel simply contains a list of all components that
     * any of its contained factory factories would contain.
     *
     * @inheritDoc
     */
    public function buildPanel(User $user, string $page, string $route, ?User $viewee, array $routeArgs, array $request = []): Panel
    {
        // I can just piece together a panel from all of the components in each
        // key converter's panels.
        $components = [];
        foreach ($this->factoryFactories as $factoryFactory) {
            $panel = $factoryFactory->buildPanel($user, $page, $route, $viewee, $routeArgs, $request);
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $components = array_replace($components, $panel->getComponents());
        }
        return new Panel($page, $route, $components);
    }
}
