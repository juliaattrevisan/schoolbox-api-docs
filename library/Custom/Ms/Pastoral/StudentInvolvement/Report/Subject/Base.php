<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Ms\Pastoral\StudentInvolvement\Report\Subject;

use Custom\Ms\Pastoral\StudentInvolvement\Report\Base as BaseReport;

/**
 * Base representation of report data for all subject reports.
 */
abstract class Base extends BaseReport
{
    /**
     * External data (class name and description)
     */
    protected array $externalData;

    /**
     * @return array
     */
    public function getExternalData(): array
    {
        return $this->externalData;
    }

    /**
     * @param array $externalData
     *
     * @return $this
     */
    public function setExternalData(array $externalData): self
    {
        $this->externalData = $externalData;
        return $this;
    }
}
