<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
        return '`' . str_replace('`', '``', $string) . '`';
    }
}
