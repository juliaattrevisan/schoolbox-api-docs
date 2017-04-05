<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database\Quoter\Dblib;

use Alaress\Database\Quoter\Base;

/**
 * Class Quoter
 */
class Quoter extends Base
{
    /**
     * @inheritdoc
     */
    public function quoteIdentifier($string)
    {
        return '`'.str_replace('`', '``', $string).'`';
    }
}
