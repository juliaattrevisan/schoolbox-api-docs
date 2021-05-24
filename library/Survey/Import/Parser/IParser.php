<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Survey\Import\Parser;

interface IParser extends \Evaluation\Import\Parser\IParser
{
    /**
     * @return string
     */
    public function getStart();

    /**
     * @return string
     */
    public function getFinish();

    /**
     * @return int
     */
    public function getAnonymousResult();

    /**
     * @return int
     */
    public function getNotifyAuthor();
}
