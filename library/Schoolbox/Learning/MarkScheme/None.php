<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Schoolbox\Learning\Assessment\IResponse;

class None extends AbstractMarkScheme
{
    public const SLUG = 'none';

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

    /**
     * This mark type has no human-readable mark, so always return null.
     *
     * @param string|null $formInput
     * @return string|null
     */
    public function convertFormInputToHumanReadableMark(?string $formInput): ?string
    {
        return null;
    }

    /**
     * This mark type has no form input, so always return null.
     *
     * @param string|null $humanReadableMark
     * @return string|null
     */
    public function convertHumanReadableMarkToFormInput(?string $humanReadableMark): ?string
    {
        return null;
    }
}
