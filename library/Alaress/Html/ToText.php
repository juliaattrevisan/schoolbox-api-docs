<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * Overriding to allow single quote
     * @inheritDoc
     */
    public function __construct($html = '', $options = [])
    {
        parent::__construct($html, $options);
        $this->htmlFuncFlags = ENT_QUOTES | ENT_HTML5;
    }

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
