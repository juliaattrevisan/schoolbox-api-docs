<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\Term\Close;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\Term as AssessmentTermFilter;

/**
 * A factory for building 'Term Close' filter objects
 */
class Factory extends AssessmentTermFilter\Factory
{

    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        $nonExtraordinarySortedTerms = $this->getNonExtraordinarySortedTerms();
        $allTerms = $this->getAllSortedTerms();

        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $allTerms,
            $this->getCurrentTerm($nonExtraordinarySortedTerms),
            $this->getPreviousTerm($nonExtraordinarySortedTerms)
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter, new Collection($this->getAllSortedTerms()->getColumnValues('id')));
    }
}
