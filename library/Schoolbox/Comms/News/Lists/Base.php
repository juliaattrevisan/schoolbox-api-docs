<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Lists;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\IArticleResolver;
use Schoolbox\Comms\News\Helper;
use Schoolbox\Comms\News\Mapper;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Comms\News\Statistics;
use Schoolbox\Discussion\Comment;
use Schoolbox\GenericCollection;
use Schoolbox\Comms\News\Topic;
use Schoolbox\User;

/**
 * Class Base
 *
 * Generates a set of articles for a particular topic
 * Also figures out whether the viewing user has saved any of those articles for later
 *
 * @package Schoolbox\Comms\News\Lists
 */
abstract class Base
{

    /**
     * System default for the number of items per page
     * Typically connects to search_type_limit
     *
     * @var int
     */
    protected $defaultLimit;


    /**
     * @var Mapper
     */
    protected $newsMapper;


    /**
     * @var Topic\Mapper
     */
    protected $topicMapper;


    /**
     * @var string
     */
    protected $readMoreText;


    /**
     * var bool
     */
    protected $saveForLaterEnabled;


    /**
     * @var SaveForLater\Mapper
     */
    protected $saveForLaterMapper;


    /**
     * @var Statistics\Mapper
     */
    protected $statisticsMapper;


    /**
     * @var Statistics\Cumulative\Mapper
     */
    protected $statisticsCumulativeMapper;


    /**
     * @var IConnector
     */
    protected $databaseInternal;


    /**
     * @var IArticleResolver
     */
    protected $articleResolver;


    /**
     * @var Comment\Mapper
     */
    protected $commentMapper;


    /**
     * @var Audience\Mapper
     */
    protected $audienceMapper;


    /**
     * @var bool
     */
    private $commentsIsEnabled;


    /**
     * Lists constructor.
     *
     * @param int                          $defaultLimit
     * @param Topic\Mapper                 $topicMapper
     * @param Mapper                       $newsMapper
     * @param string                       $readMoreText
     * @param bool                         $saveForLaterEnabled
     * @param SaveForLater\Mapper          $saveForLaterMapper
     * @param Statistics\Mapper            $statisticsMapper
     * @param Statistics\Cumulative\Mapper $statisticsCumulativeMapper
     * @param IConnector                   $databaseInternal
     * @param IArticleResolver             $articleResolver
     * @param Comment\Mapper               $commentMapper
     * @param Audience\Mapper              $audienceMapper
     * @param bool                         $commentsIsEnabled
     */
    public function __construct(
        int $defaultLimit,
        Topic\Mapper $topicMapper,
        Mapper $newsMapper,
        string $readMoreText,
        bool $saveForLaterEnabled,
        SaveForLater\Mapper $saveForLaterMapper,
        Statistics\Mapper $statisticsMapper,
        Statistics\Cumulative\Mapper $statisticsCumulativeMapper,
        IConnector $databaseInternal,
        IArticleResolver $articleResolver,
        Comment\Mapper $commentMapper,
        Audience\Mapper $audienceMapper,
        bool $commentsIsEnabled
    ) {
        $this->defaultLimit               = $defaultLimit;
        $this->topicMapper                = $topicMapper;
        $this->newsMapper                 = $newsMapper;
        $this->readMoreText               = $readMoreText;
        $this->saveForLaterEnabled        = $saveForLaterEnabled;
        $this->saveForLaterMapper         = $saveForLaterMapper;
        $this->statisticsMapper           = $statisticsMapper;
        $this->statisticsCumulativeMapper = $statisticsCumulativeMapper;
        $this->databaseInternal           = $databaseInternal;
        $this->articleResolver            = $articleResolver;
        $this->commentMapper              = $commentMapper;
        $this->audienceMapper             = $audienceMapper;
        $this->commentsIsEnabled          = $commentsIsEnabled;
    }


    /**
     * Find the article ids that were saved by $user in accordance with policy setting
     *
     * @param User    $user
     * @param bool    $saveForLaterEnabled
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    protected function findSavedArticles(User $user, bool $saveForLaterEnabled): array
    {
        if ($saveForLaterEnabled) {
            $savedArticleIds = (new SaveForLater\Lists($this->saveForLaterMapper, $this->newsMapper))->findSavedIdsForUser($user->getId());

            return array_fill_keys($savedArticleIds, SaveForLater\Mapper::SAVED);
        }

        return [];
    }


    /**
     * Populate each article's metadata instance
     *
     * @param GenericCollection $articles
     *
     * @return GenericCollection
     * @throws \InvalidArgumentException
     */
    protected function populateMetadata(GenericCollection $articles): GenericCollection
    {
        $commentCounts = [];

        if ($this->commentsIsEnabled) {
            $commentCounts = $this->commentMapper->getCommentCountByThreadIds(
                Helper::getThreadIdsFromCollection($articles),
                $this->commentMapper::ARTICLE_ID
            );
        }

        $this->loadCommentCounts($commentCounts, $articles);

        /* Other metadata stuff can be listed here
        *
        * $this->addMoreMetadata($articles);
        * $this->addEvenMoreMetadata($articles);
        */

        return $articles;
    }


    /**
     * Load the count of comments for each article in $articles
     * using pass-by-reference
     *
     * @param array             $commentCounts
     * @param GenericCollection $articles
     *
     * @return void
     */
    private function loadCommentCounts(array $commentCounts, GenericCollection $articles): void
    {
        /** @var Model $article */
        foreach ($articles as $article) {
            if ($article->getDiscussionThread()) {
                $article->getMetadata()->setCommentCount((int) $commentCounts[$article->getId()]);
            }
        }
    }


    /**
     * Load a set of hydrated articles having known their ids
     *
     * @param array       $articleIds
     * @return GenericCollection
     */
    protected function loadArticleByIds(array $articleIds) : GenericCollection
    {
        $records = $this->newsMapper->findByColumn('id', $articleIds)->indexBy('id');

        $articles = $this->newsMapper->createCollection();

        foreach ($articleIds as $articleId) {
            $record = $records->getOffset($articleId);

            if (null !== $record) {
                $articles->add($records->getOffset($articleId));
            }
        }

        return $articles;
    }


    /**
     * From a slug, figure out what topic matches it
     *
     * By default, select the All tab...
     * but try to find the topic whose slug matches the url param
     *
     * @param Collection|Topic\Model[]  $topics
     * @param null|string         $slug
     * @return int
     */
    protected function determineSelectedTopicId(Collection $topics, ?string $slug): int
    {
        $selectedTopicId = Mapper::TOPIC_ALL_ID;

        if ($slug === null || trim($slug) === '') {
            return $selectedTopicId;
        }

        foreach ($topics as $topic) {
            if ($topic->getSlug() === $slug) {
                return $topic->getId();
            }
        }

        return $selectedTopicId;
    }
}
