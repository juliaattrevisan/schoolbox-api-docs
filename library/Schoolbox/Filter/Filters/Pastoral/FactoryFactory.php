<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral;

use InvalidArgumentException;
use Schoolbox\Filter\AbstractFactoryFactory;
use Schoolbox\Filter\IFactory;
use Schoolbox\Pastoral;
use Schoolbox\User;

/**
 * Factory class responsible for creating factory classes for pastoral filters.
 */
class FactoryFactory extends AbstractFactoryFactory
{
    /**
     * @inheritdoc
     */
    protected const ENTITY = Pastoral\Mapper::class;

    /**
     * @inheritdoc
     */
    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory
    {
        return match ($filterDefinitionClass) {
            Creator\Definition::class => new Creator\Factory(
                $this->getOptionMapper(),
                $this->getDefaultsMapper(),
                $this->getSelectionMapper(),
            ),
            Student\Definition::class => new Student\Factory(
                $this->getOptionMapper(),
                $this->getDefaultsMapper(),
                $this->getSelectionMapper(),
            ),
            Type\Definition::class => new Type\Factory(
                $this->getOptionMapper(),
                $this->getDefaultsMapper(),
                $this->getSelectionMapper(),
                $this->getMapperFactory()->get(Pastoral\Type\Mapper::class),
            ),
            Severity\Definition::class => new Severity\Factory(
                $this->getOptionMapper(),
                $this->getDefaultsMapper(),
                $this->getSelectionMapper(),
                $this->getMapperFactory()->get(Pastoral\Severity\Mapper::class),
            ),
            Action\Definition::class => new Action\Factory(
                $this->getOptionMapper(),
                $this->getDefaultsMapper(),
                $this->getSelectionMapper(),
            ),
            default => throw new InvalidArgumentException(
                'No filter factory registered for class: ' . $filterDefinitionClass
            ),
        };
    }

    /**
     * @inheritdoc
     */
    public function checkTeacherFolders(array $filters, User $viewee): array
    {
        return $filters;
    }
}
