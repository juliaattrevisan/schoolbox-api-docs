<?php
/**
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\TemporalState;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter;

/**
 * A factory for building 'TemporalState' filter objects
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
            $this->getTemporalStates()
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter, $this->getTemporalStates());
    }


    /**
     * @return Collection|string[]
     */
    private function getTemporalStates()
    {
        return new Collection([
            Definition::KEY_UNPUBLISHED,
            Definition::KEY_UPCOMING,
            Definition::KEY_OPEN,
            Definition::KEY_OVERDUE,
            Definition::KEY_CLOSED,
        ]);
    }

}
