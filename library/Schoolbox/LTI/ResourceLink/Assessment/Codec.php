<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\ResourceLink\Assessment;

use Schoolbox\LTI\Util\UriBasedCodec;

/**
 * Codec for assessment resource link identifiers from an assessment.
 *
 * Encodes an assessment ID into a URI which represents the assessment, and vice
 * versa.
 *
 * (Note that the URI may not actually be a valid URL.)
 */
class Codec extends UriBasedCodec
{
    /**
     * @inheritdoc
     */
    protected function retrieveBasePath(): string
    {
        return '/lti/assessment';
    }
}
