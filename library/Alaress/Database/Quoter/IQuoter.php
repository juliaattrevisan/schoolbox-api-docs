<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database\Quoter;

/**
 * Interface for providing various database quote and escape utility methods.
 *
 * An equivalent of `PDO::quote()` is deliberately not included - use query
 * parameters instead.
 */
interface IQuoter
{
    /**
     * Quotes $string for interpolation into a column, table, etc. name in a query.
     *
     * @param  string $string
     * @return string
     */
    public function quoteIdentifier($string);

    /**
     * Escapes _ and % characters within $string, so they don't have unintended
     * effects in LIKE queries.
     *
     * This doesn't quote $string for safe use in string-interpolated queries,
     * it only escapes LIKE chars.
     *
     * @param  string $string
     * @return string
     */
    public function escapeForLike($string);
}
