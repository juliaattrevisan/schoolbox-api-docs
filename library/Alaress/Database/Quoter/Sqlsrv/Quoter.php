<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Database\Quoter\Sqlsrv;

use Alaress\Database\Quoter\Base;

/**
 * Class Quoter
 */
class Quoter extends Base
{
    /**
     * Sqlsrv identifiers are quoted with open/close square brackets. Either of
     * these can be escaped with another copy of the same character.
     *
     * @param string $string
     * @return string
     */
    public function quoteIdentifier($string)
    {
        return '[' . strtr($string, ['[' => '[[', ']' => ']]',]) . ']';
    }
}
