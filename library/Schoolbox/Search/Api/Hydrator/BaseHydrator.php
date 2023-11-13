<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Hydrator;

use Alaress\Html\ToText;
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
     * Maximum length of a description as returned by this hydrator.
     */
    public const DESCRIPTION_LENGTH = 300;

    /**
     * For use in multibyte string manipulations.
     */
    public const ENCODING = 'UTF-8';

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
     * Convert HTML to text, being careful not to introduce any artifacts that could prevent the search from working.
     * @param string $html - A full or partial HTML document.
     * @returns string - A text-only representation of the supplied HTML.
     */
    public function htmlToText(string $html): string
    {
        // Replace any image 'alt' text that looks like a filename with a blank string.
        // We do this to avoid filenames placed in image 'alt' text ending up in the search result context text.
        // Test regex here: https://www.phpliveregex.com/p/KkN#tab-preg-replace
        $html = preg_replace('/ (alt|ALT)=("|\')\S+\.\S{3,6}("|\')/', '', $html);

        // Set line width to zero (unrestricted) to avoid introducing newlines into the text-only string.
        // Extra newlines prevent the search from working if the keyword spans multiple lines.
        // Note this method was chosen over Scrub::html2text() because that method results in table cell content in
        // neighboring cells being concatenated into a single string, while ToText separates cell content with a space.
        // (e.g. '<td>Key</td><td>word</td>' becomes 'Keyword' when Scrub::html2text() is used).
        return (new ToText($html, ['width' => 0, 'do_links' => 'none']))->getText();
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
}
