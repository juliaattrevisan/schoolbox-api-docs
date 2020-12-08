<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\CurriculumNode;

use Schoolbox\Filter;

/**
 * Factory for the curriculum node filter.
 */
class Factory extends Filter\AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition($filter, $this->getOptionMapper(), $this->getDefaultsMapper());
    }


    /**
     * @inheritdoc
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter);
    }
}
