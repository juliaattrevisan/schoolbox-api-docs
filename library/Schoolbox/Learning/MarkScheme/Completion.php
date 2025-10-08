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

    public const TITLE = 'Completion';


    /**
     * This type is not comparable numerically.
     *
     * @var bool
     */
    public const QUANTITATIVE = false;


    public const HUMAN_READABLE_INCOMPLETE  = 'Incomplete';
    public const HUMAN_READABLE_COMPLETE    = 'Complete';



    /**
     * @inheritdoc
     */
    public function getHumanReadableMark($normalisedMark, ?IResponse $response = null): ?string
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
     * @return float|null
     */
    public function getNormalisedMark($humanReadableMark, ?IResponse $response = null): ?float
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
    public function convertFormInputToHumanReadableMark(?string $formInput): ?string
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
    public function convertHumanReadableMarkToFormInput(?string $humanReadableMark): ?string
    {
        return null;
    }
}
