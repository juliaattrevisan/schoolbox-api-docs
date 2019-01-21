<?php
/**
 * Lists
 *
 * Generates a set of articles for a particular topic
 * Also figures out whether the viewing user has saved any of those articles for later
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Database\Connector\IConnector;
use Alaress\Paginator\Paginator as BasePaginator;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience\IArticleResolver;
use Schoolbox\Comms\News\Audience\Segment\Strategy\Folder;
use Schoolbox\Comms\News\Statistics\ImpressionsConversionsOpens;
use Schoolbox\GenericCollection;
use Schoolbox\News\Topic;
use Schoolbox\User;

class Lists
{

    /**
     * System default for the number of items per page
     * Typically connects to search_type_limit
     *
     * @var int
     */
    private $defaultLimit;

    /**
     * @var Mapper
     */
    private $newsMapper;

    /** @var string */
    private $readMoreText;

    /** @var bool */
    private $saveForLaterEnabled;

    /**
     * @var SaveForLater\Mapper
     */
    private $saveForLaterMapper;

    /**
     * @var Statistics\Mapper
     */
    private $statisticsMapper;

    /**
     * @var Statistics\Cumulative\Mapper
     */
    private $statisticsCumulativeMapper;

    /**
     * @var IConnector
     */
    private $databaseInternal;

    /**
     * @var IArticleResolver
     */
    private $articleResolver;


    /**
     * Lists constructor.
     *
     * @param int                          $defaultLimit
     * @param Mapper                       $newsMapper
     * @param string                       $readMoreText
     * @param bool                         $saveForLaterEnabled
     * @param SaveForLater\Mapper          $saveForLaterMapper
     * @param Statistics\Mapper            $statisticsMapper
     * @param Statistics\Cumulative\Mapper $statisticsCumulativeMapper
     * @param IConnector                   $databaseInternal
     * @param IArticleResolver             $articleResolver
     */
    public function __construct(
        int $defaultLimit,
        Mapper $newsMapper,
        string $readMoreText,
        bool $saveForLaterEnabled,
        SaveForLater\Mapper $saveForLaterMapper,
        Statistics\Mapper $statisticsMapper,
        Statistics\Cumulative\Mapper $statisticsCumulativeMapper,
        IConnector $databaseInternal,
        IArticleResolver $articleResolver
    ) {
        $this->defaultLimit               = $defaultLimit;
        $this->newsMapper                 = $newsMapper;
        $this->readMoreText               = $readMoreText;
        $this->saveForLaterEnabled        = $saveForLaterEnabled;
        $this->saveForLaterMapper         = $saveForLaterMapper;
        $this->statisticsMapper           = $statisticsMapper;
        $this->statisticsCumulativeMapper = $statisticsCumulativeMapper;
        $this->databaseInternal           = $databaseInternal;
        $this->articleResolver            = $articleResolver;
    }


    /**
     * Find a list of articles that are somehow related to $topicId
     * This wraps around the hyperdrive to abstract away some pagination details
     *
     * The list consists of articles that fulfil this criteria, which accounts for all the articles that are
     * presentable to the audience:
     *
     *   - has a publish_at date
     *   - has been accepted for publication by the moderator
     *   - has not yet expired
     *   - has not been deleted
     *
     * This equates to articles in the states "Awaiting Publication", "Published", "Expired" (Archived)
     *
     * The n-n relational between articles and topics precludes proper usage of findAll()
     * with a limit - it doesn't count properly
     *
     * @param string             $urlPrefix
     * @param Querystring        $params
     * @param User               $user
     * @param Collection|Topic[] $topics
     * @param int|null           $year
     * @param int|null           $month
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \OutOfRangeException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function findForTopic(string $urlPrefix, Querystring $params, User $user, Collection $topics, ?int $year, ?int $month): array
    {
        /* News Topics were never obtainable by slug
         * We always fetched everything, then manually sorted by slug
         * Inherit this situation, improvise and adapt (or just use ids...)
         */
        $topicId = $this->determineSelectedTopicId($topics, $params->getSlug());

        /* Articles that were audienced to $user */
        $articleIdsForUser = $this->articleResolver->findArticlesForUser($user);

        /* $articleIds:   Articles in $topicId that were audienced to $user
         * $availableIds: The total available articles that were audienced to user
         *
         * Component config can set "All" as its limit, filtered to the list of articles that can be seen
         */
        $folderId = $params->getFolderId();
        $articleIds = $this->newsMapper->findArticleIdsForTopic($articleIdsForUser, $topicId, $params->getLimit(), $params->getPageNum(), $year, $month, $folderId);
        $availableIds = $this->newsMapper->findArticleIdsForTopic($articleIdsForUser, $topicId, 0, 0, $year, $month, $folderId);

        $articles = $this->loadArticleByIds($articleIds, $params, $user);

        /* Get an accompanying paginator
         */
        $url = "{$urlPrefix}?{$params}";

        /* Limits and offsets
         * Apply either the $limit provided, or use the system default
         *
         *     $limit === int:  use the value of the int for the number of items per page
         *     $limit === null: use system default for number of items per page
         *     $limit === 0:    explicitly get all the items and disregard pagination ('all' is cast to zero)
         */
        $paginator = null;

        if ($params->getLimit()) {
            $paginator = $this->generatePaginator(
                $url,
                $params->getLimit(),
                \count($availableIds)
            );
        } elseif ($params->getLimit() === null) {
            $paginator = $this->generatePaginator(
                $url,
                $this->defaultLimit,
                \count($availableIds)
            );
        }

        /* Figure out the articles that this user has saved for later
         */
        $savedArticleIds = $this->findSavedArticles($user, $this->saveForLaterEnabled);

        /* These pieces are needed to represent an article, due to Save For Later
         */
        return [
            'articles' => $articles,
            'readMore' => $this->readMoreText,
            'saveForLater' => $this->saveForLaterEnabled,
            'savedArticleIds' => $savedArticleIds,
            'unsaved' => SaveForLater\Mapper::UNSAVED,

            /* Because this is really convenient
             */
            'articleIdsForUser' => $articleIdsForUser,

            /* For the paginator
             */
            'currentUrl' => $url,
            'paginator' => $paginator,
        ];
    }


    /**
     * From a slug, figure out what topic matches it
     *
     * By default, select the All tab...
     * but try to find the topic whose slug matches the url param
     *
     * @param Collection|Topic[]  $topics
     * @param null|string         $slug
     * @return int
     */
    private function determineSelectedTopicId(Collection $topics, ?string $slug): int
    {
        $selectedTopicId = Mapper::TOPIC_ALL_ID;

        foreach ($topics as $topic) {
            if ($topic->getSlug() === $slug) {
                return $topic->getId();
            }
        }

        return $selectedTopicId;
    }

    /**
     * Find the article ids that were saved by $user in accordance with policy setting
     *
     * @param User    $user
     * @param bool    $saveForLaterEnabled
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    private function findSavedArticles(User $user, bool $saveForLaterEnabled): array
    {
        if ($saveForLaterEnabled) {
            $savedArticleIds = (new SaveForLater\Lists($this->saveForLaterMapper, $this->newsMapper))->findSavedIdsForUser($user->getId());

            return array_fill_keys($savedArticleIds, SaveForLater\Mapper::SAVED);
        }

        return [];
    }


    /**
     * Generate a standard paginator to go with your article list
     *
     * @param string $url
     * @param int    $limit
     * @param int    $total
     * @return Paginator|null
     * @throws \OutOfRangeException
     */
    private function generatePaginator(string $url, int $limit, int $total): ?Paginator
    {
        /* No limit specified; no pagination required
         */
        if (!$limit) {
            return null;
        }

        $paginator = (new BasePaginator())
            ->setPageSize($limit)
            ->setTotal($total);

        $urlPaginator = (new Paginator())
            ->setPaginator($paginator)
            ->setUrl($url)
            ->setParam(Querystring::PAGE_NUMBER);

        return $urlPaginator;
    }


    /**
     * @param Querystring $params
     * @param User        $user
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     * @throws \InvalidArgumentException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function findArticlesForHomepage(Querystring $params, User $user) :array
    {
        $articleTable = Mapper::getInstance()->getTable();

        $articleIds = $this->articleResolver->findArticlesForAudience(
            Folder::getEntityType(),
            $params->getFolderId(),
            Audience\Model::RELATIONSHIP_SELF,
            true,
            [ "$articleTable.publish_at" => 'DESC', "$articleTable.created_at" => 'DESC'],
            $params->getLimit()
        );

        $idProperty = $this->newsMapper->getIdentifierProperty();

        /** @var GenericCollection|Model[] $articles */
        $articles = $this->loadArticleByIds($articleIds, $params, $user)->indexBy($idProperty);

        // Load audiences so module auth rules work in rendered templates
        $rootSegments = Audience\Mapper::getInstance()->findForArticles($articles->getPropertyValues($idProperty));
        foreach ($rootSegments as $rootSegment) {
            $articles[$rootSegment->getCommsNewsArticle()->getId()]->getAudience()[$rootSegment->getId()] = $rootSegment;
        }

        return [
            'articles' => $articles,
            'readMore' => $this->readMoreText,
            'saveForLater' => $this->saveForLaterEnabled,
            'savedArticleIds' => $this->findSavedArticles($user, $this->saveForLaterEnabled),
            'unsaved' => SaveForLater\Mapper::UNSAVED,
        ];
    }

    /**
     * @param array $articleIds
     * @param Querystring $params
     * @param User $user
     * @return \Schoolbox\GenericCollection
     * @throws \Schoolbox\Mapper\Exception
     */
    private function loadArticleByIds(array $articleIds, Querystring $params, User $user) :\Schoolbox\GenericCollection
    {
        $records = $this->newsMapper->findByColumn('id', $articleIds)->indexBy('id');

        $articles = $this->newsMapper->createCollection();

        foreach ($articleIds as $articleId) {
            $record = $records->getOffset($articleId);

            if (null !== $record) {
                $articles->add($records->getOffset($articleId));

                /* Statistics: save an impression record
                 * Load the referrer value from $params
                 * Also increment a tally elsewhere
                 */

                (new ImpressionsConversionsOpens(
                    $this->databaseInternal,
                    $this->statisticsMapper,
                    $this->statisticsCumulativeMapper
                ))->saveImpression(
                    $record,
                    $user->getId(),
                    $user->getRole()->getType(),
                    $user->getRole()->getId(),
                    $params->getReferrer()
                );
            }
        }

        return $articles;
    }
}
