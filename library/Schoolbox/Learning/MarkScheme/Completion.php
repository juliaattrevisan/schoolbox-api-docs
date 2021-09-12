<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme;

use Schoolbox\Learning\Assessment\IResponse;
use Schoolbox\Learning\Assessment\Task\Response\Model as TaskResponse;

class Completion extends AbstractMarkScheme
{
    public const SLUG = 'completion';

    const TITLE = 'Completion';

    const HUMAN_READABLE_INCOMPLETE  = 'Incomplete';
    const HUMAN_READABLE_COMPLETE    = 'Complete';

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
        // Does the response not exist, or is not submitted, or explicitly marked as incomplete?
        if (!$response || !$response->isSubmitted() || ($response instanceof TaskResponse && !$response->getMarkedComplete())) {
            return self::HUMAN_READABLE_INCOMPLETE;
        }
        return self::HUMAN_READABLE_COMPLETE;
    }

    /**
     * Returns null (this ensures Summators always ignore)
     *
     * @param string         $humanReadableMark eg 'Completed'
     * @param IResponse|null $response
     * @return null
     */
    public function getNormalisedMark($humanReadableMark, IResponse $response = null)
    {
        if (null === $response || ($response instanceof TaskResponse && !$response->getMarkedComplete())) {
            return self::NORMALISED_MARK_MIN;
        }
        return self::NORMALISED_MARK_MAX;
    }

    /**
     * This mark type has no way to convert a form input to a mark, so always
     * return null.
     *
     * @param string|null $formInput
     * @return string|null
     */
    public function convertFromFormInputToHumanReadableMark(?string $formInput): ?string
    {
        return null;
    }

    /**
     * This mark type has no way to convert a mark to a form input, so always
     * return null.
     *
     * @param string|null $humanReadableMark
     * @return string|null
     */
    public function convertFromHumanReadableMarkToFormInput(?string $humanReadableMark): ?string
    {
        return null;
    }
}
