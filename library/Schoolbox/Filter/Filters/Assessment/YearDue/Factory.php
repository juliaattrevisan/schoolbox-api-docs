<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\YearDue;

use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\IKeyConverter;
use Schoolbox\Filter;

/**
 * A factory for building 'YearDue' filter objects
 */
class Factory extends AbstractFactory
{

    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * No dynamic options or user-selections relevant.
     *
     * @param Filter\Model $filter
     * @return IDefinition|Definition
     */
    public function buildDefinition(Filter\Model $filter): IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper()
        );
    }


    /**
     * Returns a key converter, able to convert between concrete keys and the entities they represent
     *
     * @param Filter\Model $filter
     * @return IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): IKeyConverter
    {
        return new KeyConverter($filter);
    }
}
