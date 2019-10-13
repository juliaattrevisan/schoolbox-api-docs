<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Event;

use InvalidArgumentException;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters;
use Schoolbox\Filter\IFactory;
use Schoolbox\User;

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
        if ($filterDefinitionClass === EventType\Definition::class) {
            return new EventType\Factory(
                $this->getOptionMapper(),
                $this->getDefaultsMapper(),
                $this->getSelectionMapper(),
                $this->getMapperFactory()->get(EventTypeMapper::class)
            );
        }
        if ($filterDefinitionClass === Campus\Definition::class) {
            return new Campus\Factory(
                $this->getOptionMapper(),
                $this->getDefaultsMapper(),
                $this->getSelectionMapper(),
                $this->getMapperFactory()->get(CampusMapper::class)
            );
        }

        throw new InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }


    /**
     * This filter factory factory doesn't care about teacher folders.
     *
     * @inheritDoc
     */
    public function checkTeacherFolders(array $filters, User $viewee): array
    {
        return $filters;
    }
}
