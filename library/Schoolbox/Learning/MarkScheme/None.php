<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Schoolbox\Learning\Assessment\IResponse;

class None extends AbstractMarkScheme
{
    const TITLE = 'None';

    /**
     * This type is not comparable numerically.
     *
     * @var bool
     */
    const QUANTITATIVE = false;

    /**
     * @inheritdoc
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        return null;
    }

    /**
     * No normalised mark returned
     *
     * @param string         $humanReadableMark
     * @param IResponse|null $response
     * @return null
     */
    public function getNormalisedMark($humanReadableMark, IResponse $response = null)
    {
        return null;
    }
}
