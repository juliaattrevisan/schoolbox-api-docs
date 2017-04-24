<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Term;

use Schoolbox\Filter\AbstractRunTimeOptions;
use Schoolbox\Assessment\Term;
use Schoolbox\GenericCollection;

/**
 * No implementation. All options are pre-defined
 */
class RunTimeOptions extends AbstractRunTimeOptions
{

    /**
     * @param GenericCollection|Term\Model[]|null $terms
     */
    public function __construct(GenericCollection $terms = null)
    {
        // Translates between Terms (because we fetched by term.code) and term.ids
        $termIds = $terms instanceof GenericCollection
            ? $terms->getColumnValues('id')
            : null;

        parent::__construct($termIds);
    }

}
