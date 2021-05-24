<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Assess\Import\Parser;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Evaluation\Import\Parser\Base as EvaluationParser;

/**
 * Quiz-specific implementation.
 *
 * If the file format changes, just extend the most recent parser and over-ride the relevant getter
 */
class V160 extends EvaluationParser implements IParser
{
    /**
     * @return string|null
     */
    public function getWorkType()
    {
        return null;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getPublishDate()
    {
        return $this->createDateFromString($this->getXml()->start);
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getOpenDate()
    {
        return $this->createDateFromString($this->getXml()->start);
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate()
    {
        return $this->createDateFromString($this->getXml()->finish);
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getCloseDate()
    {
        return $this->createDateFromString($this->getXml()->finish);
    }

    /**
     * @return null
     */
    public function getDefaultOpenSpan()
    {
        // This field did not exist prior to 16.5
        return null;
    }

    /**
     * @return int
     */
    public function getWeight()
    {
        return (int)$this->getXml()->weight;
    }

    /**
     * @return int
     */
    public function getDuration()
    {
        return (int)$this->getXml()->duration;
    }

    /**
     * @return int
     */
    public function getMarkVisibility()
    {
        return (int)$this->getXml()->markvisibility;
    }

    /**
     * @param string $dateString
     * @param string $format
     * @return Alaress_Date_Format
     */
    protected function createDateFromString($dateString, $format = Alaress_Date_Format::DATABASE_FORMAT)
    {
        if (empty($dateString)) {
            return new Alaress_Date_NullDate();
        }
        return new Alaress_Date_Format($dateString, $format);
    }
}
