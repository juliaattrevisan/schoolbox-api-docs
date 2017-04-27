<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database\Quoter\Mysql;

use Alaress\Database\Quoter\Base;

/**
 * Class Quoter
 */
class Quoter extends Base
{
    /**
     * MySQL identifiers are quoted with backticks. A backtick can be escaped
     * with another backtick.
     *
     * @param string $string
     * @return string
     */
    public function quoteIdentifier($string)
    {
        return '`'.str_replace('`', '``', $string).'`';
    }
}
