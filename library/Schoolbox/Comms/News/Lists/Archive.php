<?php
/**
 * Archive
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News\Lists;

use Alaress\Paginator\Paginator as BasePaginator;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Mapper;
use Schoolbox\Comms\News\Paginator;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\News\Topic;
use Schoolbox\User;

class Archive extends Base
{

    /**
     * Note: This is now used only in News Archive
     *
     * Find a list of articles that are somehow related to $topicId
     * This wraps around the hyperdrive to abstract away some pagination details
     *
     * The list consists of articles that fulfil this criteria, which accounts for all the articles that are
     * presentable to the audience:
     *
     * If the user is a superuser or a news moderator,
     * Article must be published
     * and one of the following conditions must be met:
     *    - you're the author
     *    - you approved the article
     *    - you're in the audience
     *
     * Everyone else,
     * Article must be published
     * and one of the following conditions must be met:
     *    - you're the author
     *    - you're in the audience
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
     *
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
    public function find(string $urlPrefix, Querystring $params, User $user, Collection $topics, ?int $year, ?int $month): array
    {
        /* News Topics were never obtainable by slug
         * We always fetched everything, then manually sorted by slug
         * Inherit this situation, improvise and adapt (or just use ids...)
         */
        $topicId = $params->getSlug() !== null ? $this->determineSelectedTopicId($topics, $params->getSlug()) : Mapper::TOPIC_ALL_ID;

        /* $articleIds:   Articles in $topicId that were audienced to $user
         * $availableIds: The total available articles that were audienced to user
         * $articleIdsForUser: Article id's that were audienced to user and the ones that the user has created (if any)
         *
         * Component config can set "All" as its limit, filtered to the list of articles that can be seen
         */
        $articleIdsForUser = [];

        $folderId = $params->getFolderId();

        /* No folder specified: you're getting all the archived news
         * for the News Archive list
         */
        if (null === $folderId) {

            /* If the user is a superuser or a news moderator
             *
             * Article must be published
             * and one of the following conditions must be met:
             *    - you're the author
             *    - you approved the article
             *    - you're in the audience
             *
             * Everyone else
             *
             * Article must be published
             * and one of the following conditions must be met:
             *    - you're the author
             *    - you're in the audience
             */
            $articleIdsForUser = array_unique(array_merge(
                $this->articleResolver->findArticlesForUser($user),
                $this->newsMapper->findAuthoredOrAcceptedArticleIds($user, false)
            ));
        }

        $articleIds = $this->newsMapper->findArticleIdsForTopic(
            $articleIdsForUser,
            $topicId,
            $params->getLimit(),
            $params->getPageNum(),
            $year,
            $month,
            $folderId
        );

        $availableIds = $this->newsMapper->findArticleIdsForTopic(
            $articleIdsForUser,
            $topicId,
            0,
            0,
            $year,
            $month,
            $folderId
        );

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
            'articles' => $this->populateMetadata($articles),
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
}
