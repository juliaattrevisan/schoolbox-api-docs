<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Survey\Import\Parser;

use Evaluation\Import\Parser\Base as BaseParser;

/**
 * Survey-specific implementation.
 *
 * If the file format changes, just extend the most recent parser and over-ride the relevant getter
 */
class V160 extends BaseParser implements IParser
{
    /**
     * @return string
     */
    public function getStart()
    {
        return (string)$this->getXml()->start;
    }

    /**
     * @return string
     */
    public function getFinish()
    {
        return (string)$this->getXml()->finish;
    }

    /**
     * @return int
     */
    public function getAnonymousResult()
    {
        // Not implemented in 16.0 export
        return 0;
    }

    /**
     * @return int
     */
    public function getNotifyAuthor()
    {
        // Not implemented in 16.0 export
        return 0;
    }
}
