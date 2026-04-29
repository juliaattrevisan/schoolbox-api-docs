<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Feedback\MapperProxy as DueWorkFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Translator\ModelToArray\Factory as DueWorkFeedbackTranslatorFactory;
use Schoolbox\Learning\Assessment\Quiz\Feedback\MapperProxy as QuizFeedbackMapperProxy;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Translator\ModelToArray\Factory as QuizFeedbackTranslatorFactory;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Badge\Award\Mapper as BadgeAwardMapper;
use Schoolbox\Learning\Badge\Award\Translator\ModelToArray\Factory as BadgeAwardTranslatorFactory;
use Schoolbox\Learning\Moment\Mapper as EvidenceMapper;
use Schoolbox\Learning\Moment\Feed\Translator\ModelToArray;
use Schoolbox\Learning\Moment\Translator\ModelToArray\Factory as EvidenceTranslatorFactory;
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
    private const FEED_ITEMS_PER_PAGE = 2;


    /**
     * Number of comments to show for per feed item
     */
    private const FEED_COMMENTS_PER_ITEM = 2;


    /**
     * Constructor
     *
     * @param EvidenceMapper $evidenceMapper
     * @param BadgeAwardMapper $badgeAwardMapper
     * @param CommentMapper $commentMapper
     * @param IConnector $database
     * @param DueWorkFeedbackMapperProxy $returnDataSource
     * @param QuizFeedbackMapperProxy $attemptDataSource
     * @param AssessmentMapperProxy $assessmentDataSource
     * @param Visibility $visibility
     */
    public function __construct(
        private EvidenceMapper $evidenceMapper,
        private BadgeAwardMapper $badgeAwardMapper,
        private CommentMapper $commentMapper,
        private IConnector $database,
        private DueWorkFeedbackMapperProxy $returnDataSource,
        private QuizFeedbackMapperProxy $attemptDataSource,
        private AssessmentMapperProxy $assessmentDataSource,
        private Visibility $visibility
    ) {
    }


    /**
     * Retrieve a user's learning moments and badges, in one API endpoint.
     *
     * @param User $user
     * @since 20.2
     *
     * @api   GET /learning/evidenceFeed/user/{userId}/
     */
    public function getFeedForUser(User $user): void
    {
        $encodedFilters = $this->requestQuery->get('filter');
        $encodedCursor  = $this->requestQuery->get('cursor');
        $cursor         = new Cursor(
            $this->database,
            $user,
            $this->getUser(),
            (($encodedFilters === '') ? null : $encodedFilters),
            null,
            self::FEED_ITEMS_PER_PAGE,
            $this->getConfigLoader(),
        );

        $hydrator        = new Hydrator(
            $this->evidenceMapper,
            $this->badgeAwardMapper,
            $this->returnDataSource,
            $this->attemptDataSource,
            $this->assessmentDataSource,
            $this->visibility,
            $this->getUser()
        );
        $prefixGenerator = new PrefixGenerator($this->getConfigLoader());
        $translator      = new ModelToArray(
            (new EvidenceTranslatorFactory(
                $this->routeGenerator,
                $this->authorizer,
                $this->getUser(),
                $this->commentMapper,
                $prefixGenerator
            ))->buildWithComments(self::FEED_COMMENTS_PER_ITEM),
            (new BadgeAwardTranslatorFactory(
                $this->routeGenerator,
                $this->authorizer,
                $this->getUser(),
                $prefixGenerator,
            ))->build(),
            (new DueWorkFeedbackTranslatorFactory(
                $this->getConfigLoader(),
                $this->routeGenerator,
                $this->authorizer,
                $this->getUser(),
            ))->build(),
            (new QuizFeedbackTranslatorFactory(
                $this->routeGenerator,
                $this->authorizer,
                $this->getUser(),
            ))->build()
        );

        $page = (new Paginator($cursor, $hydrator, self::FEED_ITEMS_PER_PAGE))->retrievePage($encodedCursor);

        $mappedData = $page->getData()
                           ->map($translator)
                           ->reindex();

        $this->response = $this->responseAsJson(
            [
                'data'     => $mappedData,
                'metadata' => [
                    'count'  => $page->getCount(),
                    'cursor' => [
                        'current' => $page->getCurrentCursor(),
                        'next'    => $page->getNextCursor(),
                    ],
                ],
            ]
        );
    }
}
