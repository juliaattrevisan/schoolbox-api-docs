<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Lists;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Paginator\Paginator as BasePaginator;
use InvalidArgumentException;
use OutOfRangeException;
use PDOException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Lookup\ReadMore;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\Paginator;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Comms\News\ValidHasReadMore;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;

use function array_slice;
use function count;
use function in_array;

/**
 * Class Archive
 *
 * Get a list of articles for the News Archive
 *
 * @package Schoolbox\Comms\News\Lists
 */
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
     * @param string             $urlPrefix
     * @param Querystring        $params
     * @param User               $user
     * @param int|null           $year
     * @param int|null           $month
     * @param bool               $getYearsAndMonths Whether to include data re: which years/months have articles
     * @return array
     * @throws InvalidColumnException
     * @throws InvalidArgumentException
     * @throws OutOfRangeException
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws DefinitionException
     */
    public function find(string $urlPrefix, Querystring $params, User $user, ?int $year, ?int $month, bool $getYearsAndMonths): array
    {
        if ($params->getFolderId() !== null) {
            $folderFeed = new FolderFeed($this->articleResolver);
            $articleIdFilter = $folderFeed->find($params->getFolderId(), false);
        } else {
            $userFeed = new UserFeed($this->newsMapper, $this->articleResolver);
            $articleIdFilter = $userFeed->find($user, Model::STATE_PUBLIC, false);
        }

        // Find all years and months that have articles so we can populate the sidebar
        $yearsAndMonths = $getYearsAndMonths
            ? $this->newsMapper->findYearsAndMonthsWithArticles($articleIdFilter, Model::STATE_PUBLIC)
            : null;

        $slag = $params->getSlug() ?: News\Mapper::TOPIC_ALL_SLUG;

        $availableIds = $this->newsMapper->findArticleIdsByFilters(
            $articleIdFilter,
            $slag,
            null,
            null,
            $year,
            $month,
            News\Model::STATE_PUBLIC
        );

        // Get an accompanying paginator
        $url = "{$urlPrefix}?{$params}";

        /* Limits and offsets
         * Apply either the $limit provided, or use the system default
         *
         *     $limit === int:  use the value of the int for the number of items per page
         *     $limit === null: use system default for number of items per page
         *     $limit === 0:    explicitly get all the items and disregard pagination ('all' is cast to zero)
         */
        $articleIds = $availableIds;
        if ((string)$params->getLimit() === '0') {
            $paginator = null;
        } else {
            $limit   = $params->getLimit() ?? $this->defaultLimit;
            $pageNum = in_array($params->getPageNum(), [null, '', 0, '0', 1, '1'], true) ? 1 : $params->getPageNum();
            $articleIds = array_slice($articleIds, ($pageNum - 1) * $limit, $limit);
            $paginator = $this->generatePaginator(
                $url,
                $limit,
                count($availableIds)
            );
        }

        $articles = $this->loadArticleByIds($articleIds);

        /* Attachment counts for each article in this set
         */
        $attachmentCounts = $this->fileMapper->findCounts($articleIds);

        /* Disregard the sticky bit for each article in the Archive
         */
        foreach ($articles as $article) {
            /* Artificially represent the presence of one or more files without having to hydrate them
             */
            $article->setAttachedFilesCount($attachmentCounts[$article->getId()] ?? 0);
            $article->setSticky(false);
        }

        // Figure out the articles that this user has saved for later
        $savedArticleIds = $this->findSavedArticles($user, $this->saveForLaterEnabled);

        /* Build a lookup of Read More links for each article id, which aren't json but
         * Model instances that don't comprehend the Read More status like this
         */
        $readMores = (new ReadMore($this->readMoreText, $this->commentsIsEnabled))->generate($articles);

        // These pieces are needed to represent an article, due to Save For Later
        return [
            'articles' => $this->populateMetadata($articles),
            'attachmentCounts' => $attachmentCounts,
            'readMores' => $readMores,
            'saveForLater' => $this->saveForLaterEnabled,
            'savedArticleIds' => $savedArticleIds,
            'unsaved' => SaveForLater\Mapper::UNSAVED,

            /* Because this is really convenient
             */
            'articleIdsForUser' => $availableIds,

            /* For the paginator
             */
            'currentUrl' => $url,
            'paginator' => $paginator,
            'yearsAndMonths' => $yearsAndMonths,
        ];
    }


    /**
     * Generate a standard paginator to go with your article list
     *
     * @param string $url
     * @param int    $limit
     * @param int    $total
     * @return Paginator|null
     * @throws OutOfRangeException
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

        return (new Paginator())
            ->setPaginator($paginator)
            ->setUrl($url)
            ->setParam(Querystring::PAGE_NUMBER);
    }
}
