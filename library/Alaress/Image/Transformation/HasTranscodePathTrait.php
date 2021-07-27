<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Image\Transformation;

trait HasTranscodePathTrait
{
    /**
     * @param string      $resourcePath
     * @param string|null $cacheKey
     * @return string
     */
    public function getTranscodePath(string $resourcePath, ?string $cacheKey): string
    {
        return ($cacheKey && strpos($resourcePath, '-') === false) ? $resourcePath . '-' . $cacheKey : $resourcePath;
    }
}
