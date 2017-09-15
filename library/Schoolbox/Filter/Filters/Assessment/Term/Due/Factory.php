<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\Term\Due;

use \Schoolbox\Filter;
use \Schoolbox\Filter\Filters\Assessment\Term as AssessmentTermFilter;

/**
 * A factory for building 'Term Due' filter objects
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
        $sortedTerms = $this->getSortedTerms();

        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $sortedTerms,
            $this->getCurrentTerm($sortedTerms),
            $this->getPreviousTerm($sortedTerms)
        );
    }
}
