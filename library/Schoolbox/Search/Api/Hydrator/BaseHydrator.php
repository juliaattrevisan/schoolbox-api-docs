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

    /**
     * Truncates `$content` around the first occurrence of `$keyword`, to a max
     * of `$maxChars / 2` characters either side.
     *
     * If this truncation slices characters off the start/end of `$content`,
     * show truncation marks (...) before/after the truncated content.
     *
     * @param string $keyword  The search keyword.
     * @param string $content  The content to truncate.
     * @param int    $maxChars The max character count to include in the
     *                         description: allows for an equal number of
     *                         characters before and after the keyword.
     *
     * @return string|null
     */
    public function truncateAroundKeyword(string $keyword, string $content, int $maxChars): ?string
    {
        // As always, encoding is UTF-8
        $encoding = 'UTF-8';

        // Is $keyword there? Don't truncate anything if it's not
        $strpos = mb_stripos($content, $keyword, 0, $encoding);
        if ($strpos !== false) {
            // How many chars before and after the keyword match?
            $charsBefore = (int)($maxChars / 2);
            $charsAfter  = $maxChars - $charsBefore;

            // Determine whether to add truncation marks (...)
            $keywordStrlen = mb_strlen($keyword, $encoding);
            $contentStrlen = mb_strlen($content, $encoding);

            // Show marks before if the first character is after the start
            $cutStart       = $strpos - $charsBefore;
            $startTruncated = true;
            if ($cutStart < 0) {
                // Reduce `$maxChars` so that no more than half of `$maxChars`
                // are after the keyword
                $maxChars       += $cutStart;
                $cutStart       = 0;
                $startTruncated = false;
            }

            // Show marks after if the last character is before the end
            $finishTruncated = true;
            $cutFinish       = $strpos + $keywordStrlen + $charsAfter;
            if ($cutFinish > $contentStrlen) {
                $finishTruncated = false;
            }

            // And truncate
            $truncated = trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    mb_strcut($content, $cutStart, $maxChars, $encoding),
                )
            );
            if ($startTruncated) {
                $truncated = '...' . $truncated;
            }
            if ($finishTruncated) {
                $truncated = $truncated . '...';
            }
            return $truncated;
        }
        return null;
    }
}
