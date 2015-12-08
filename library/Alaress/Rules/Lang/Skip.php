<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Rules\Lang;

/**
 * A language construct for skipping application of all subsequent rules in a
 * ruleset.
 *
 * @package Alaress\Rules\Lang
 */
class Skip implements Construct
{
    /**
     * @inheritDoc
     */
    public function getSummary()
    {
        return 'SKIP';
    }

    /**
     * Skip rules don't apply to a field, but one is required anyway.
     *
     * @return string
     */
    public function getField()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function apply($inputValue)
    {
        throw new SkipException();
    }
}
