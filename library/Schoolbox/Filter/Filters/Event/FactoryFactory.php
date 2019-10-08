<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event;

use InvalidArgumentException;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters;
use Schoolbox\Filter\IFactory;

/**
 * Factory class responsible for creating factory classes for event filters.
 */
class FactoryFactory extends Filter\AbstractFactoryFactory
{
    /**
     * @inheritdoc
     */
    protected const ENTITY = EventMapper::class;


    /**
     * @inheritDoc
     */
    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory
    {
        if (!class_exists($filterDefinitionClass)) {
            throw new InvalidArgumentException('No filter definition found for class: ' . $filterDefinitionClass);
        }

        // Registry of filters definitions, and their extra dependencies
        throw new InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }
}
