<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database\PredicateBuilder;

/**
 * Interface for predicate builder implementations.
 */
interface IPredicateBuilder
{
    const OPERATOR_EQUALS     = '=';
    const OPERATOR_NOT_EQUALS = '<>';
    const OPERATOR_LIKE       = 'LIKE';
    const OPERATOR_NOT_LIKE   = 'NOT LIKE';
    const OPERATOR_LT         = '<';
    const OPERATOR_LTE        = '<=';
    const OPERATOR_GT         = '>';
    const OPERATOR_GTE        = '>=';
    const OPERATOR_INTERSECT  = 'AND';
    const OPERATOR_UNION      = 'OR';
}
