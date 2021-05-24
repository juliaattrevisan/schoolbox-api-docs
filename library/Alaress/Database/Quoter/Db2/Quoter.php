<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Database\Quoter\Db2;

use Alaress\Database\Quoter\Base;

/**
 * Class Quoter
 */
class Quoter extends Base
{
    /**
     * DB2 identifiers are quoted with double quotes. A double quote can be
     * escaped with another double quote.
     *
     * @param string $string
     * @return string
     */
    public function quoteIdentifier($string)
    {
        return '"' . str_replace('"', '""', $string) . '"';
    }
}
