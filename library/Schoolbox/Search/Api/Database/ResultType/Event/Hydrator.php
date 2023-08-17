<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Event;

use Alaress\Html\ToText;
use Schoolbox\Comms\News;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 */
class Hydrator extends BaseHydrator
{
    /**
     * Maximum length of a description as returned by this hydrator.
     */
    private const DESCRIPTION_LENGTH = 300;

    /**
     * This hydrator's base type.
     */
    public const BASE_TYPE = 'event';

    /**
     * Constructor
     *
     * @param UserMapper   $userMapper
     * @param FolderMapper $folderMapper
     */
    public function __construct(
        private UserMapper $userMapper,
        private FolderMapper $folderMapper,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getBaseType(): string
    {
        return self::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, array $matches, User $viewer): array
    {
        // Find all news articles which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);
        $eventIds        = array_map(fn(RawResult $match) => $match->id, $acceptedMatches);
        if (empty($eventIds)) {
            return [];
        }

        // Find the given events
        $events = (new \Calendar())->getDayEvents(
            $viewer,
            $viewer,
            null,
            null,
            ['id' => $eventIds]
        )->indexBy('eventId');

        $folderIds = $events->map(static function (\Calendar\Event\Type\Day\Event $event){
            return $event->getCategoryCollection()?->first()->getCategoryId();
       });
        $folders = $this->folderMapper->findByColumn('id', $folderIds)->indexBy('id');
        $authorIds = $events->getPropertyValues('authorId');
        $authors = $this->userMapper->findByColumn('id', $authorIds)->indexBy('id');

        // And map them to a full result
        $fullResults = array_map(
            function (RawResult $match) use ($events, $keyword, $folders, $authors) {
                /** @var \Calendar\Event\Type\Day\Event $event */
                $event = $events->offsetGet($match->id);
                // TODO: check this!
                // The article may have been found if the keyword matched a
                // HTML attribute or its value in the blurb or body.
                // Filter out matches which were found to match the keyword
                // on the query, but were found to not match either:
                // * the title
                // * the generated description
//                $description = $this->generateDescriptionForArticle($keyword, $event);
//                if (!isset($description) && !str_contains($match->title, $keyword)) {
//                    return null;
//                }
                return new FullResult(
                    $match,
                    $match->id,
                    $match->title,
                    $this->buildBaseUrl()
                         ->withPath($event->getPath())
                         ->__toString(),
                    $match->type,
                    $match->relevance,
                    $event->getStartTime(),
                    // TODO: is this correct?
                    $event->getCategoryCollection()?->first() ? $folders->offsetGet($event->getCategoryCollection()?->first()->getCategoryId()) : null,
                    null,
                    null,
                    self::BASE_TYPE,
                    $event->getDetail(),
                    $authors->offsetGet($event->getAuthorId()),
                    [],
                    []
                );
            },
            $matches,
        );
        return array_filter($fullResults, fn(?FullResult $x) => isset($x));
    }

    /**
     * Generate a description for the given article, by finding the text near
     * the keyword in:
     * * the blurb first, if there is one
     * * the body otherwise
     *
     * @param string     $keyword The search keyword.
     * @param News\Model $article The article to generate a description for.
     * @return string|null
     */
    private function generateDescriptionForArticle(string $keyword, News\Model $article): ?string
    {
        // Find keyword in blurb, then body
        if ($article->getBlurb()) {
            $truncatedBlurb = $this->truncateAroundKeyword(
                $keyword,
                (new ToText($article->getBlurb()))->getText(),
                self::DESCRIPTION_LENGTH,
            );
            if ($truncatedBlurb) {
                return $truncatedBlurb;
            }
        }
        if ($article->getBody()) {
            return $this->truncateAroundKeyword(
                $keyword,
                (new ToText($article->getBody()))->getText(),
                self::DESCRIPTION_LENGTH,
            );
        }

        return null;
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
    private function truncateAroundKeyword(string $keyword, string $content, int $maxChars): ?string
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
