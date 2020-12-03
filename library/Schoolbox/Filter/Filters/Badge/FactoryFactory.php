<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);


namespace Schoolbox\Filter\Filters\Badge;


use InvalidArgumentException;use Schoolbox\Collection;use Schoolbox\Filter;use Schoolbox\Filter\IFactory;use Schoolbox\User;


/**
 * Factory class responsible for building badge filter factories.
 */
class FactoryFactory extends Filter\AbstractFactoryFactory
{
    public function buildFilterFactoryForFilter(string $filterDefinitionClass) : IFactory
    {
        // TODO: Implement buildFilterFactoryForFilter() method.
    }


    /**
     * Badges have nothing to do with folders, thus `$filters` is returned
     * unchanged.
     *
     * @param array $filters
     * @param User  $viewee
     * @return array
     */
    public function checkTeacherFolders(array $filters, User $viewee) : array
    {
        return $filters;
    }
}
