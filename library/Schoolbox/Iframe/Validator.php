<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Iframe;

class Validator
{
    /**
     * @param string $safeIframeSourceList
     */
    public function __construct(private string $safeIframeSourceList)
    {
    }


    /**
     * Validate an external url for iframing, using our config field in admin to decide
     * if we should allow that url or not:
     *
     * safe_iframe_source_list
     *
     * @param string $url
     * @return bool
     */
    public function isValid(string $url): bool
    {
        // Explode on \R to include all types of newline chars.
        foreach (preg_split('/\R/', $this->safeIframeSourceList) as $source) {
            $source = trim((string)$source);

            /* An empty line for whatever reason; continue looking in the rest of the list
             */
            if ('' === $source) {
                continue;
            }

            $sourceEscaped = preg_quote($source, '%');

            /* It matched at least one of the sources declared to be "safe"
             */
            if (preg_match('%' . $sourceEscaped . '%', $url)) {
                return true;
            }
        }

        return false;
    }
}
