<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Assess\Import\Parser;

use Alaress_Date_Format;

/**
 * v16.5 quiz variations from current quiz base
 */
class V165 extends V160
{
    /**
     * @return Alaress_Date_Format
     */
    public function getPublishDate()
    {
        return $this->createDateFromString($this->getXml()->publishdate);
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getOpenDate()
    {
        return $this->createDateFromString($this->getXml()->opendate);
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate()
    {
        return $this->createDateFromString($this->getXml()->duedate);
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getCloseDate()
    {
        return $this->createDateFromString($this->getXml()->closedate);
    }

    /**
     * @return int|null
     */
    public function getDefaultOpenSpan()
    {
        $defaultOpenSpan = (string)$this->getXml()->defaultopenspan;
        return '' === $defaultOpenSpan ? null : 0;
    }
}
