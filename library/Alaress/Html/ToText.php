<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Html;
use Html2Text\Html2Text;

/**
 * Class ToText
 * @package Alaress\Html
 */
class ToText extends Html2Text
{
    /**
     * I don't want strtoupper conversion
     *
     * @param  string $str Text to convert
     * @return string Converted text
     */
    protected function strtoupper($str)
    {
        return $str;
    }
}
