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
use Schoolbox\Learning\Assessment\Visibility;
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
     * Constructor
     *
     * @param EvidenceMapper             $evidenceMapper
     * @param BadgeAwardMapper           $badgeAwardMapper
     * @param CommentMapper              $commentMapper
     * @param IConnector                 $database
     * @param DueWorkFeedbackMapperProxy $returnDataSource
     * @param AssessmentMapperProxy      $assessmentDataSource
     * @param Visibility                 $visibility
     */
    public function __construct(
        private EvidenceMapper $evidenceMapper,
        private BadgeAwardMapper $badgeAwardMapper,
        private CommentMapper $commentMapper,
        private IConnector $database,
        private DueWorkFeedbackMapperProxy $returnDataSource,
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
     * @api   GET /learning/evidence/user/{userId}/feed
     */
    public function getFeedForUser(User $user): void
    {
        $encodedFilters = $this->request->query->get('filter');
        $filter = json_decode($encodedFilters, true);

        // Validate $filter array.
        if (
            !array_key_exists('feedItemTypes', $filter) ||
            !array_key_exists('tags', $filter) ||
            !is_array($filter['feedItemTypes']) ||
            !is_array($filter['tags'])
        ) {
            throw new \InvalidArgumentException("Invalid value for 'filter' parameter.");
        }

        $filtersForCursor = [];

        // Feed item types in the $filter array are those displayed in the UI:
        //   {'assessments', 'achievements', 'goals', 'learning-moments'}.
        // These must be translated into filters that will be understood by the Cursor.
        foreach ($filter['feedItemTypes'] as $feedItemType) {
            switch ($feedItemType) {
                case 'assessments':
                    $filtersForCursor[] = ['feedItemType' => 'dueWorkFeedback'];
                    break;
                case 'achievements':
                    $filtersForCursor[] = ['feedItemType' => 'badgeAward', 'badgeStatus' => 'achievement'];
                    break;
                case 'goals':
                    $filtersForCursor[] = ['feedItemType' => 'badgeAward', 'badgeStatus' => 'goal'];
                    break;
                case 'learning-moments':
                    $filtersForCursor[] = ['feedItemType' => 'evidence'];
                    break;
                default:
                    // Do nothing.
            }
        }

        $encodedCursor  = $this->request->query->get('cursor');
        $cursor         = new Cursor(
            $this->database,
            $user,
            $this->getUser(),
            $filtersForCursor,
            null,
            self::FEED_ITEMS_PER_PAGE,
            $this->getConfigLoader(),
        );

        $hydrator        = new Hydrator(
            $this->evidenceMapper,
            $this->badgeAwardMapper,
            $this->returnDataSource,
            $this->assessmentDataSource,
            $this->visibility,
            $this->getUser()
        );
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
            ))->build(),
            (new DueWorkFeedbackTranslatorFactory(
                $this->router,
                $this->authorizer,
                $this->getUser(),
            ))->build()
        );

        $data = $cursor->retrieveResults($encodedCursor);

        $mappedData = $hydrator->hydrateResults($data)
                               ->map($translator)
                               ->reindex();

        $this->response->content->set(
            $this->buildJsonOutput(
                [
                    'data'     => $mappedData,
                    'metadata' => [
                        'count'  => $cursor->retrieveResultCount(),
                        'cursor' => [
                            'current' => $encodedCursor ?? $cursor->current($data),
                            'next'    => $cursor->next($data),
                        ],
                    ],
                ]
            )
        );
    }
}
