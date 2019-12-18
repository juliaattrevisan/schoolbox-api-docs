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
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Querystring;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use UnderflowException;
use UnexpectedValueException;
use function strtolower;
use const PHP_URL_HOST;
use const PHP_URL_SCHEME;

class Controller extends Base
{
    /** @var News\Mapper */
    private $newsMapper;

    /** @var News\Topic\Mapper */
    private $topicMapper;

    /** @var ArticleResolver */
    private $articleResolver;


    /**
     * @param News\Mapper       $newsMapper
     * @param News\Topic\Mapper $topicMapper
     * @param ArticleResolver   $articleResolver
     */
    public function __construct(
        News\Mapper $newsMapper,
        News\Topic\Mapper $topicMapper,
        ArticleResolver $articleResolver
    ) {
        $this->newsMapper      = $newsMapper;
        $this->topicMapper     = $topicMapper;
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
     * @throws DefinitionException
     * @throws NotFound
     * @noinspection PhpUnused
     */
    public function getFeedForUser(User $user): void
    {
        $userFeed = new News\Lists\UserFeed($this->newsMapper, $this->articleResolver);

        $this->renderArticlesAsXml($userFeed->find($user), $this->getRequest()->query->get(Querystring::TOPIC_SLUG));
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
     * @throws DefinitionException
     * @throws NotFound
     * @noinspection PhpUnused
     */
    public function getFeedForFolder(Folder\Model $folder): void
    {
        $folderFeed = new News\Lists\FolderFeed($this->articleResolver);

        $this->renderArticlesAsXml(
            $folderFeed->find($folder->getId()),
            $this->getRequest()->query->get(Querystring::TOPIC_SLUG)
        );
    }


    /**
     * Render the set of articles to an XML feed
     *
     * @param int[]             $articleIdFilter
     * @param string|null       $topicSlug
     * @param Folder\Model|null $folder
     * @throws DefinitionException
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws InvalidComponent
     * @throws NotFound
     * @throws PDOException
     * @throws ServiceNotFound
     * @throws UnderflowException
     */
    private function renderArticlesAsXml(array $articleIdFilter, ?string $topicSlug, Folder\Model $folder = null): void
    {
        $this->assertValidTopic($topicSlug);

        $availableIds = $this->newsMapper->findArticleIdsByFilters(
            $articleIdFilter,
            $topicSlug,
            null,
            null,
            null,
            null,
            News\Model::STATE_PUBLISHED
        );

        $articles = $this->newsMapper->findByColumn('id', $availableIds, ['order' => ['publish_at' => 'DESC']]);

        $this->getResponse()->content->set($this->buildXmlOutput('@commsNewsFeed/rss.twig', [
            'folder'     => $folder,
            'title'      => $this->getConfigLoader()->get('title'),
            'schoolName' => $this->getConfigLoader()->get('school_name'),
            'baseUrl'    => $this->getRequest()->url->get(PHP_URL_SCHEME) . '://' . $this->getRequest()->url->get(PHP_URL_HOST),
            'url'        => $this->getRequest()->url->get(),
            'articles'   => $articles,
        ]));
    }


    /**
     * Asserts that a topic is valid, throws 404 if not found
     *
     * @param string|null $topic
     * @throws NotFound
     */
    private function assertValidTopic(?string $topic): void
    {
        if ($topic === null || strtolower($topic) === News\Mapper::TOPIC_ALL_SLUG) {
            return;
        }
        try {
            $this->topicMapper->findOneByColumn('name', $topic);
        } catch (MapperException $e) {
            throw new NotFound("No topic found with name '$topic'", $e->getCode(), $e);
        }
    }
}
