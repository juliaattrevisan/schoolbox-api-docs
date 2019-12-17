<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\Feed;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Web\Exception\InvalidComponent;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Audience\Segment\Strategy;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use function array_merge;
use function array_unique;
use function count;
use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

class Controller extends Base
{
    /** @var News\Mapper */
    private $newsMapper;

    /** @var ArticleResolver */
    private $articleResolver;


    /**
     * Controller constructor.
     *
     * @param News\Mapper     $newsMapper
     * @param ArticleResolver $articleResolver
     */
    public function __construct(
        News\Mapper $newsMapper,
        ArticleResolver $articleResolver
    ) {
        $this->newsMapper      = $newsMapper;
        $this->articleResolver = $articleResolver;
    }


    /**
     * Generate RSS feed containing articles that are:
     * * audienced either to the specified $user
     * * authored by specified user, or
     * * moderated by specified user
     *
     * @api          /news/feed/{key}?topic={slug}
     *
     * @param User $user
     * @throws InvalidComponent
     * @throws ServiceNotFound
     * @throws InvalidColumnException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @noinspection PhpUnused
     */
    public function getFeedForUser(User $user): void
    {
        $relevantArticles = array_unique(
            array_merge(
                $this->articleResolver->findArticlesForUser($user),
                $this->newsMapper->findAuthoredOrAcceptedArticleIds($user)
            )
        );

        if (!count($relevantArticles)) {
            $articleIds = [];
        } else {
            $topic = $this->getRequest()->query->get(News\Querystring::TOPIC_SLUG);
            if ($topic === News\Mapper::TOPIC_ALL_SLUG) {
                $topic = null;
            }
            $articleIds = $this->newsMapper->findArticleIdsByFilters(
                $relevantArticles,
                $topic,
                null,
                null,
                null,
                null,
                News\Model::STATE_PUBLIC,
                null
            );
        }

        $this->renderArticlesAsXml($articleIds);
    }

    /**
     *
     * Generate RSS feed containing articles audienced either to $folder, and which the user
     * has permission to view.
     *
     * @api          /news/feed/{key}/{folderId:\d+}?topic={slug}
     *
     * @param Folder\Model $folder
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws InvalidComponent
     * @throws PDOException
     * @throws ServiceNotFound
     * @throws UnderflowException
     * @noinspection PhpUnused
     */
    public function getFeedForFolder(Folder\Model $folder): void
    {
        $articlesForFolder = $this->articleResolver->findArticlesForAudience(
            Strategy\Folder::getEntityType(),
            $folder->getId(),
            News\Audience\Model::RELATIONSHIP_SELF
        );

        if (!count($articlesForFolder)) {
            $articleIds = [];
        } else {
            $articleIds = $this->newsMapper->findArticleIdsByFilters(
                $articlesForFolder,
                $this->getRequest()->query->get(News\Querystring::TOPIC_SLUG),
                null,
                null,
                null,
                null,
                News\Model::STATE_PUBLIC,
                $folder->getId()
            );
        }

        $this->renderArticlesAsXml($articleIds, $folder);
    }


    /**
     * Render the set of articles to an XML feed
     *
     * @param int[]             $articleIds
     * @param Folder\Model|null $folder
     * @throws ServiceNotFound
     * @throws InvalidComponent
     */
    private function renderArticlesAsXml(array $articleIds, Folder\Model $folder = null): void
    {
        $articles = $this->newsMapper->findByColumn('id', $articleIds, ['order' => ['publish_at' => 'DESC']]);

        $this->getResponse()->content->set($this->buildXmlOutput('@commsNewsFeed/rss.twig', [
            'folder'     => $folder,
            'title'      => $this->getConfigLoader()->get('title'),
            'schoolName' => $this->getConfigLoader()->get('school_name'),
            'baseUrl'    => $this->getRequest()->url->get(PHP_URL_SCHEME) . '://' . $this->getRequest()->url->get(PHP_URL_HOST),
            'url'        => $this->getRequest()->url->get(),
            'articles'   => $articles,
        ]));
    }
}
