<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\AllowAsGoal;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter;

/**
 * A factory for building 'AllowAsGoal' filter objects
 */
class Factory extends AbstractFactory
{

    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->getAllowAsGoal()
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter, $this->getAllowAsGoal());
    }


    /**
     * @return Collection|string[]
     */
    private function getAllowAsGoal()
    {
        return new Collection(
            [
                Definition::KEY_ALLOWED,
                Definition::KEY_NOT_ALLOWED,
            ]
        );
    }
}
