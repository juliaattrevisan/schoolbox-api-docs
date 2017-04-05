<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database\Quoter\Sqlsrv;

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
        // [identifier] is supported whether setting QUOTED_IDENTIFIER is on or off.
        // If someone for some reason used [ or ] in an identifier:
        // escape with an extra of the same character
        return '['.strtr($string, ['[' => '[[', ']' => ']]',]).']';
    }
}
