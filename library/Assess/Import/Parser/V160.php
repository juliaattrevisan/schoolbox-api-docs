<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Assess\Import\Parser;

use Alaress_Date_Format;
use Evaluation\Import\Parser\Base as EvaluationParser;
use Schoolbox\Learning\Assessment\IAssessable;

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
     * @return string
     */
    public function getMarkVisibility(): string
    {
        return match ((int) $this->getXml()->markvisibility) {
            1 => IAssessable::MARK_VISIBILITY_STUDENT,
            2 => IAssessable::MARK_VISIBILITY_PARENT_STUDENT,
            3 => IAssessable::MARK_VISIBILITY_PARENT, // This option did not exist in version 16, so should not be encountered during import.
            default => IAssessable::MARK_VISIBILITY_PRIVATE,
        };
    }

    /**
     * @param string $dateString
     * @param string $format
     * @return null|Alaress_Date_Format
     */
    protected function createDateFromString($dateString, $format = Alaress_Date_Format::DATABASE_FORMAT)
    {
        if (empty($dateString)) {
            return null;
        }
        return new Alaress_Date_Format($dateString, $format);
    }
}
