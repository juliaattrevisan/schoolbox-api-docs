<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\LTI\Util;

/**
 * Codec for context identifiers.
 *
 * Encodes a folder ID into a URI which represents the folder, and vice versa.
 *
 * (Note that the URI may not actually be a valid URL.)
 */
class ContextCodec extends UriBasedCodec
{
    /**
     * @inheritdoc
     */
    protected function retrieveBasePath(): string
    {
        return '/lti/folder';
    }
}
