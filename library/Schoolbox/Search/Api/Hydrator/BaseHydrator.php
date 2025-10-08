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
use Schoolbox\Search\Api\Database\Index\BaseBuilder;
use Schoolbox\Url\Uri;
use Schoolbox\User;

/**
 * Base hydrator, with a simple implementation of `accepts`.
 */
abstract class BaseHydrator implements IHydrator
{
    /**
     * Maximum length of a description as returned by this hydrator.
     */
    public const DESCRIPTION_LENGTH = 300;

    public const BASE_TYPE = 'TO_BE_OVERRIDDEN';

    public const SUB_TYPES = [];

    /**
     * For use in multibyte string manipulations.
     */
    public const ENCODING = 'UTF-8';

    /**
     * This hydrator accepts any raw result whose:
     * * type matches self::BASE_TYPE
     * * type starts with self::BASE_TYPE . '.'
     */
    public function accepts(RawResult $match): bool
    {
        // Using static:: instead of self:: ensures the value comes from the child class (if it is defined there).
        return $match->type === static::BASE_TYPE || str_starts_with($match->type, static::BASE_TYPE . '.');
    }

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
     * Convert HTML to text, being careful not to introduce any artifacts that could prevent the search result text
     * from making sense in the context of the search keyword used.
     * @param string $html - A full or partial HTML document.
     * @returns string - A text-only representation of the supplied HTML.
     */
    public function htmlToText(string $html): string
    {
        // Simplicity is better here.  Previously more elaborate methods of converting HTML to text were used here,
        // but the result was more complexity and more problematic edge-cases, with little benefit.
        // See https://github.com/alaress/schoolbox/pull/29275
        return BaseBuilder::normaliseContent($html, BaseBuilder::INDEX_STRATEGY_HTML);
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
     * @param int    $maxChars The max character count to include in the description (not including the keyword length).
     *                         Allows for an equal number of characters before and after the keyword.
     *
     * @return string|null
     */
    public function truncateAroundKeyword(string $keyword, string $content, int $maxChars = self::DESCRIPTION_LENGTH): ?string
    {
        // Is $keyword there? Don't truncate anything if it's not
        $strpos = mb_stripos($content, $keyword, 0, self::ENCODING);
        if ($strpos !== false) {
            // How many chars before and after the keyword match?
            $charsBefore = (int)($maxChars / 2);
            $charsAfter  = $maxChars - $charsBefore;

            // Determine whether to add truncation marks (...)
            $keywordStrlen = mb_strlen($keyword, self::ENCODING);
            $contentStrlen = mb_strlen($content, self::ENCODING);

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
                    mb_strcut($content, $cutStart, $cutFinish - $cutStart, self::ENCODING),
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

    /**
     * For use as fallback to $this->truncateAroundKeyword().
     * E.g. $description = $this->truncateAroundKeyword($keyword, $content) ?? $this->truncate($content);
     *
     * @param string $content  The content to truncate.
     * @param int    $maxChars The maximum number of characters to return.
     * @returns string
     */
    public function truncate(string $content, int $maxChars = self::DESCRIPTION_LENGTH): string
    {
        return mb_strcut($content, 0, $maxChars, self::ENCODING);
    }

    /**
     * @param User $user
     * @return bool
     * @see BaseQueryParamBuilder/isRelevantToUser() (Similar function for QueryParamBuilders)
     */
    public function isRelevantToUser(User $user): bool
    {
        // Override this function if user permissions can render a Hydrator irrelevant to a user.
        return true;
    }
}
