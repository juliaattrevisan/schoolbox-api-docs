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
use Schoolbox\Pastoral\Mapper;
use Schoolbox\User;

/**
 * Factory class responsible for creating factory classes for pastoral filters.
 */
class FactoryFactory extends AbstractFactoryFactory
{
    /**
     * @inheritdoc
     */
    protected const ENTITY = Mapper::class;

    /**
     * @inheritdoc
     */
    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory
    {
        throw new InvalidArgumentException();
    }

    /**
     * @inheritdoc
     */
    public function checkTeacherFolders(array $filters, User $viewee): array
    {
        return $filters;
    }
}
