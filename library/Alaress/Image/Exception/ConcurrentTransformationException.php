<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Image\Exception;

use Schoolbox\Application\Exception as SchoolboxApplicationException;
use Schoolbox\Resource\Storage\Transform;

/**
 * Thrown when we attempt to generate an image transformation while the same transformation is possibly still
 * being performed by another process.
 *
 * Web Requests should catch this and display a suitable placeholder
 */
class ConcurrentTransformationException extends SchoolboxApplicationException\Exception
{
    /**
     * @param Transform\Model $transform The prior transform.
     */
    public function __construct(Transform\Model $transform)
    {
        parent::__construct(
            sprintf(
                "Another process has attempted this transform. Hash: '%s', CacheKey: '%s', State: '%s', At: '%s'.",
                $transform->getHash(),
                $transform->getCacheKey(),
                $transform->getState(),
                $transform->getUpdatedAt()->__toString(),
            ),
            0,
            null,
            503 // This should be a transient issue.
        );
    }
}
