<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Assess\Import\Parser;

use Alaress_Date_Format;

/**
 * Exposes all values that the imported needs.
 *
 * Different parsers will pull this data from different elements.
 */
interface IParser extends \Evaluation\Import\Parser\IParser
{
    /**
     * @return string|null
     */
    public function getWorkType();

    /**
     * @return Alaress_Date_Format
     */
    public function getPublishDate();

    /**
     * @return Alaress_Date_Format
     */
    public function getOpenDate();

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate();

    /**
     * @return Alaress_Date_Format
     */
    public function getCloseDate();

    /**
     * @return int
     */
    public function getWeight();

    /**
     * @return int
     */
    public function getDuration();

    /**
     * @return int
     */
    public function getMarkVisibility();

    /**
     * @return int|null
     */
    public function getDefaultOpenSpan();
}
