<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Learning\Badge\Award\Mapper as BadgeAwardMapper;
use Schoolbox\Learning\Badge\Award\Translator\ModelToArray\Factory as BadgeAwardTranslatorFactory;
use Schoolbox\Learning\Moment\Evidence\Mapper as EvidenceMapper;
use Schoolbox\Learning\Moment\Evidence\Translator\ModelToArray\Factory as EvidenceTranslatorFactory;
use Schoolbox\Learning\Moment\Feed\Translator\ModelToArray;
use Schoolbox\User;

/**
 * Controller for the learning moment feed.
 *
 * This feed encapsulates:
 * * Evidence
 * * Badge awards (achievements and goals)
 */
class Controller extends Base
{

    /**
     * Number of feed items to display before prompting user
     * with Load More button
     */
    private const FEED_ITEMS_PER_PAGE = 10;


    /**
     * Number of comments to show for per feed item
     */
    private const FEED_COMMENTS_PER_ITEM = 2;


    /**
     * Mapper used to retrieve learning moment evidence.
     */
    private EvidenceMapper $evidenceMapper;


    /**
     * Mapper used to retrieve badge awards.
     */
    private BadgeAwardMapper $badgeAwardMapper;


    /**
     * Mapper used to retrieve comments on learning moments.
     */
    private CommentMapper $commentMapper;


    /**
     * Database connector, required for the feed's cursor.
     */
    private IConnector $database;


    /**
     * Constructor
     *
     * @param EvidenceMapper   $evidenceMapper
     * @param BadgeAwardMapper $badgeAwardMapper
     * @param CommentMapper    $commentMapper
     * @param IConnector       $database
     */
    public function __construct(
        EvidenceMapper $evidenceMapper,
        BadgeAwardMapper $badgeAwardMapper,
        CommentMapper $commentMapper,
        IConnector $database
    ) {
        $this->evidenceMapper   = $evidenceMapper;
        $this->badgeAwardMapper = $badgeAwardMapper;
        $this->commentMapper    = $commentMapper;
        $this->database         = $database;
    }


    /**
     * Retrieve a user's learning moments and badges, in one API endpoint.
     *
     * @param User $user
     * @since 20.2
     *
     * @api   GET /learning/evidence/user/{userId}/feed
     */
    public function getFeedForUser(User $user): void
    {
        $encodedCursor   = $this->request->query->get('cursor');
        $cursor          = new Cursor(
            $this->database,
            $user,
            $this->getUser(),
            null,
            self::FEED_ITEMS_PER_PAGE,
            $this->getConfigLoader(),
        );
        $hydrator        = new Hydrator($this->evidenceMapper, $this->badgeAwardMapper);
        $prefixGenerator = new PrefixGenerator($this->getConfigLoader());
        $translator      = new ModelToArray(
            (new EvidenceTranslatorFactory(
                $this->router,
                $this->authorizer,
                $this->getUser(),
                $this->commentMapper,
                $prefixGenerator
            ))->buildWithComments(self::FEED_COMMENTS_PER_ITEM),
            (new BadgeAwardTranslatorFactory(
                $this->router,
                $this->authorizer,
                $this->getUser(),
                $prefixGenerator,
            ))->build()
        );

        $data       = $cursor->retrieveResults($encodedCursor);

        $mappedData = $hydrator->hydrateResults($data)
            ->map($translator)
            ->reindex();

        $this->response->content->set(
            $this->buildJsonOutput([
                'data'     => $mappedData,
                'metadata' => [
                    'count'  => $cursor->retrieveResultCount(),
                    'cursor' => [
                        'current' => $encodedCursor ?? $cursor->current($data),
                        'next'    => $cursor->next($data),
                    ],
                ],
            ])
        );
    }
}
