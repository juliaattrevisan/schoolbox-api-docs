<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use InvalidArgumentException;
use Schoolbox\Learning\Assessment\IResponse;

class None extends AbstractMarkScheme
{
    const TITLE = 'None';

    /**
     * No human-readable mark returned
     *
     * @param float          $normalisedMark
     * @param IResponse|null $response
     * @throws InvalidArgumentException if normalised mark is outside valid range
     * @return string
     */
    public function getHumanReadableMark($normalisedMark, IResponse $response = null)
    {
        if(!is_null($normalisedMark)){
            $this->validateNormalisedMark($normalisedMark);
        }
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