<?php

/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Assess\Import\Parser;

/**
 * v17.0 quiz variations from current quiz base.
 *
 * The only difference between this and 16.5 is the presence of the work type field.
 */
class V170 extends V165
{
    /**
     * @return string|null
     */
    public function getWorkType()
    {
        return $this->getXml()->worktype;
    }
}
