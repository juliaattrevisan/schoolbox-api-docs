<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Event;

use Alaress\Html\ToText;
use Calendar;
use Calendar\Event\Type\Day\Event as DayEvent;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 */
class Hydrator extends BaseHydrator
{
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
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all news articles which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);
        $eventIds        = array_map(fn(RawResult $match) => $match->id, $acceptedMatches);
        if (empty($eventIds)) {
            return [];
        }

        // Find the given events
        $events = (new Calendar())->getDayEvents(
            $viewer,
            $viewer,
            null,
            null,
            ['id' => $eventIds]
        )->indexBy('eventId');

        $folderIds = $events->map(static function (DayEvent $event) {
            return $event->getCategoryCollection()?->first()->getCategoryId();
        });
        $folders   = $this->folderMapper->findByColumn('id', $folderIds)->indexBy('id');
        $authorIds = $events->getPropertyValues('authorId');
        $authors   = $this->userMapper->findByColumn('id', $authorIds)->indexBy('id');

        // And map them to a full result
        $fullResults = array_map(
            function (RawResult $match) use ($events, $folders, $authors, $keyword) {
                $event = $events->offsetGet($match->id);
                if (!$event instanceof DayEvent) {
                    return null;
                }

                $eventDetailTextOnly = (new ToText($event->getDetail()))->getText();
                $eventDetailTextOnlyLowercase = strtolower($eventDetailTextOnly);
                $keywordLowercase = strtolower($keyword);

                // Because the badge event detail HTML, this match might have come about only because the
                // keyword was found in HTML code that is invisible to the user (eg. 'style=').
                // Eliminate those kinds of matches.
                if (
                    !str_contains(strtolower($event->getTitle()), $keywordLowercase) &&
                    !str_contains(strtolower($event->getLocation()), $keywordLowercase)
                ) {
                    if (!str_contains($eventDetailTextOnlyLowercase, $keywordLowercase)) {
                        return null;
                    }
                }

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
                    $event->getCategoryCollection()?->first() ? $folders->offsetGet($event->getCategoryCollection()->first()->getCategoryId()) : null,
                    null,
                    null,
                    'calendar',
                    $this->truncateAroundKeyword($keyword, $eventDetailTextOnly) ?? $this->truncate($eventDetailTextOnly),
                    $authors->offsetGet($event->getAuthorId()),
                    [],
                    []
                );
            },
            $matches,
        );

        return array_filter($fullResults, fn(?FullResult $x) => isset($x));
    }
}
