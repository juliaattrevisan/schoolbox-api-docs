<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter;

/**
 * Responsible for determining whether a HTTP request is requesting a filtered page view.
 */
class RequestChecker
{

    /**
     * Returns whether the payload indicates we are filtering to a sub-set of available upcoming assessments
     *
     * @param array $request
     * @return bool
     */
    public function isFilteredRequest(array $request): bool
    {
        foreach ($request as $options) {
            $countSelected = 0;
            if (!\is_array($options)) {
                // This will be an empty string when no concrete options are available. eg no READ-level folders.
                // @see Codec::convertRequestToSelections()
                continue;
            }
            foreach ($options as $option) {
                if ($option) {
                    $countSelected++;
                }
            }
            if ($countSelected && $countSelected !== \count($options)) {
                return true;
            }
        }
        return false;
    }
}
