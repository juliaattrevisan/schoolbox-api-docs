<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Evaluation\Import\Parser;

/**
 * Interface common for Quiz + Survey parsing.
 *
 * Quiz/Survey -specific getters should be defined on interfaces extending this interface.
 */
interface IParser
{
    /* The version when the file format last changed.
     *
     * You only need to add a new item here (along with corresponding parser class) if the file format changes.
     * eg. if we are now in 17.5, but file formats have not changed since 16.5, then the version will still be 16.5
     * and the parser class will be V165.
     */
    const FORMAT_VERSION_160 = '16.0';
    const FORMAT_VERSION_165 = '16.5';
    const FORMAT_VERSION_170 = '17.0';

    /**
     * @return string
     */
    public function getTitle();

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return string
     */
    public function getCompletionMessage();

    /**
     * @return int
     */
    public function getMaxAttempts();
}
