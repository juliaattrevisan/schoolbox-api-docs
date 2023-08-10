<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Hydrator;

use Alaress\Image\Transformation\Storage;
use Psr\Http\Message\UriInterface;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Url\Uri;

/**
 * Base hydrator, with a simple implementation of `accepts` which requires a
 * `getBaseType` method be implemented.
 */
abstract class BaseHydrator implements IHydrator
{
    /**
     * This hydrator accepts any raw result whose:
     * * type matches `$this->getBaseType()`
     * * type starts with `$this->getBaseType() . '.'`
     */
    public function accepts(RawResult $match): bool
    {
        $baseType = $this->getBaseType();
        return $match->type === $baseType || str_starts_with($match->type, $baseType . '.');
    }

    /**
     * What is the base type this hydrator accepts?
     *
     * @return string
     */
    abstract protected function getBaseType(): string;

    /**
     * Helper for building a base URL for use across hydrators.
     *
     * @return UriInterface
     */
    protected function buildBaseUrl(): UriInterface
    {
        return new Uri();
    }

    /**
     * Helper for building a URL for an image in storage.
     *
     * @param IStorage $storage The storage file.
     * @param string   $size    The size of the image to display: default to
     *                          "square64".
     * @return UriInterface
     */
    protected function buildStorageImageUrl(
        IStorage $storage,
        string $size = Storage::TRANSFORM_SQUARE64,
    ): UriInterface {
        return $this->buildBaseUrl()
            ->withPath('/storage/image.php')
            ->withQuery(
                http_build_query([
                    'hash' => $storage->getHash(),
                    'size' => $size,
                ])
            );
    }
}
