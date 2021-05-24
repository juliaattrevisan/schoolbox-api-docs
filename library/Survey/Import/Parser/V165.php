<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Survey\Import\Parser;

/**
 * v16.5 survey variations
 */
class V165 extends V160
{
    /**
     * @return int
     */
    public function getAnonymousResult()
    {
        return (int)$this->getXml()->anonymousresult;
    }

    /**
     * @return int
     */
    public function getNotifyAuthor()
    {
        return (int)$this->getXml()->notifyauthor;
    }
}
