<?php
/**
 * Archive
 *
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
use Schoolbox\Comms\News\Paginator;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use function array_slice;
use function count;
use function in_array;

/**
 * Responsible for generating data to populate the news archive.
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
     * @return array
     * @throws InvalidColumnException
     * @throws InvalidArgumentException
     * @throws OutOfRangeException
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws DefinitionException
     */
    public function find(string $urlPrefix, Querystring $params, User $user, ?int $year, ?int $month): array
    {
        if($params->getFolderId() !== null) {
            $folderFeed = new FolderFeed($this->articleResolver);
            $articleIdFilter = $folderFeed->find($params->getFolderId());
        } else {
            $userFeed = new UserFeed($this->newsMapper, $this->articleResolver);
            $articleIdFilter = $userFeed->find($user);
        }

        $availableIds = $this->newsMapper->findArticleIdsByFilters(
            $articleIdFilter,
            $params->getSlug(),
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
        if((string)$params->getLimit() === '0'){
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

        // Figure out the articles that this user has saved for later
        $savedArticleIds = $this->findSavedArticles($user, $this->saveForLaterEnabled);

        // These pieces are needed to represent an article, due to Save For Later
        return [
            'articles' => $this->populateMetadata($articles),
            'readMore' => $this->readMoreText,
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
