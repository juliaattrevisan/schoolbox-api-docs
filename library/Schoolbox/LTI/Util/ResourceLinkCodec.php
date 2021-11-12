<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

/**
 * Codec for assessment resource link identifiers.
 *
 * Encodes an assessment ID into a URI which represents the assessment, and vice
 * versa.
 *
 * (Note that the URI may not actually be a valid URL.)
 */
class ResourceLinkCodec extends UriBasedCodec
{
    /**
     * @inheritdoc
     */
    protected function retrieveBasePath(): string
    {
        return '/lti/assessment';
    }
}
