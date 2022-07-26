<?php

/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News\SaveForLater;

use Alaress\Web\Controller\Base;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Lists\UserFeed;
use Schoolbox\Comms\News\Lookup\ReadMore;
use Schoolbox\Comms\News\Model;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\User;
use PDOException;
use UnexpectedValueException;

class Controller extends Base
{
    /**
     * @var Mapper
     */
    private $saveForLaterMapper;

    /**
     * @var News\Mapper
     */
    private $newsMapper;


    /**
     * @var News\File\Mapper
     */
    private News\File\Mapper $fileMapper;

    /** @var ArticleResolver */
    private ArticleResolver $articleResolver;

    private UserAccessMapper $userAccessMapper;

    /**
     * Controller constructor.
     *
     * @param Mapper           $saveForLaterMapper
     * @param News\Mapper      $newsMapper
     * @param News\File\Mapper $fileMapper
     * @param ArticleResolver  $articleResolver
     * @param UserAccessMapper $userAccessMapper
     */
    public function __construct(
        Mapper $saveForLaterMapper,
        News\Mapper $newsMapper,
        News\File\Mapper $fileMapper,
        ArticleResolver $articleResolver,
        UserAccessMapper $userAccessMapper
    ) {
        $this->saveForLaterMapper = $saveForLaterMapper;
        $this->newsMapper         = $newsMapper;
        $this->fileMapper         = $fileMapper;
        $this->articleResolver    = $articleResolver;
        $this->userAccessMapper   = $userAccessMapper;
    }


    /**
     * A list of articles that you saved for later
     *
     * @throws PDOException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @api GET /news/saved
     */
    public function getList(): void
    {
        $ids             = $this->saveForLaterMapper->findSavedIdsForUser($this->getUser()->getId());
        $savedArticleIds = array_map('intval', $ids);
        if ($this->isXhrRequest()) {
            // this is for turning on saved icon in news feed
            // this may contain non-accessible article ids, but should not be a problem
            $this->getResponse()->content->set($this->buildJsonOutput($savedArticleIds));
            return;
        }

        $articleIds = $savedArticleIds;

        // Check article visibility, if current user is not superuser or doesn't have news permission
        if (
            !$this->getUser()->isSuperuser()
            && $this->userAccessMapper->findServiceForUser($this->getUser(), UserAccess::SERVICE_NEWS)->count() === 0
        ) {
            // get safe saved articles
            $userFeed   = new UserFeed($this->newsMapper, $this->articleResolver);
            $articleIds = $userFeed->find($this->getUser(), Model::STATE_PUBLIC, true);

            // check saved articles but not in the feed
            $idsToCheck = array_diff($savedArticleIds, $articleIds);
            if (!empty($idsToCheck)) {
                $articleIds = array_merge(
                    $articleIds,
                    $this->newsMapper->findArticleIdsForRole($this->getUser()->getRole(), $idsToCheck)
                );
            }

            // TODO: there may remain viewable articles
        }

        $params = new News\Querystring($this->getRequest()->query);
        $params->setReferrer(News\Statistics\Mapper::REFERRER_SAVED);
        $articles = $this->newsMapper->findByColumn('id', $articleIds);

        $sidebar = new News\Sidebar($this->newsMapper, $this->getUser(), RequestUriParser::parseUrlPath($this->request->server->getArrayCopy()));

        /* Attachment counts for each article in this set
         */
        $attachmentCounts = $this->fileMapper->findCounts($articles->getPropertyValues('id'));

        foreach ($articles as $article) {
            /* Attachments aren't actually hydrated in $model, but we got the counts
             * for use in a metadata elsewhere, so we can reuse that fact here by
             * artificially representing the number of files without having the actual
             * files themselves with all their mimes etc.
             */
            $article->setAttachedFilesCount($attachmentCounts[$article->getId()] ?? 0);
        }

        /* Build a lookup of Read More links for each article id, which aren't json but
         * Model instances that don't comprehend the Read More status like this
         */
        $readMores = (new ReadMore($this->getConfigLoader()->get(ConfigMapper::_READ_MORE), $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED)))->generate($articles);

        $data = array_merge(
            [
                'articles'         => $articles,
                'attachmentCounts' => $attachmentCounts,
                'referrer'         => $params->getReferrer(),
                'readMores'        => $readMores,
                'fallbackImage'    => [
                    'type' => $this->getConfigLoader()->get('comms_news_article_list_fallback_image'),
                    'hash' => $this->getConfigLoader()->get('skin_logo_large'),
                ],
            ],
            $sidebar->generate()
        );
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsSaveForLater/index.twig', $data)->setTitle('Saved For Later')->setTab('news'));
    }


    /**
     * Add an article to a user's 'saved-for-later' list.
     *
     * No error if item is already added to list when this is called.
     *
     * @param News\Model $article
     * @api POST /news/saved/{articleId}/add
     */
    public function postAdd(News\Model $article): void
    {
        try {
            $this->addArticleToUserSavedList($article, $this->getUser());
        } catch (BaseMapper\Exception | PDOException | UnexpectedValueException $e) {
            // Server-side error, not the client's fault. Developers to be alerted.
            $this->captureException($e);
            $this->getResponse()->status->set(500);
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([]));
            return;
        }
    }


    /**
     * Remove an item from your Saved list
     *
     * No error if item was not in list when route was called.
     *
     * @param News\Model $article
     * @api POST /news/saved/{articleId}/remove
     */
    public function postRemove(News\Model $article): void
    {
        try {
            $this->removeArticleFromUserSavedList($article, $this->getUser());
        } catch (BaseMapper\Exception $e) {
            // Server-side error, not the client's fault. Developers to be alerted.
            $this->captureException($e);
            $this->getResponse()->status->set(500);
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([]));
            return;
        }
    }


    /**
     * Adds the article to the user's 'saved-for-later' list
     *
     * Returns whether the item was inserted, as opposed to already existing
     *
     * @param News\Model $article
     * @param User       $user
     * @return void
     * @throws BaseMapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function addArticleToUserSavedList(News\Model $article, User $user): void
    {
        $columnList = ['user_id' => $user->getId(), 'article_id' => $article->getId()];

        try {
            $this->saveForLaterMapper->findOneByColumnList($columnList);
        } catch (BaseMapper\Exception\Deleted $e) {
            // Was in list, but was removed. Treated as an update action, not a restore action.
            /** @var Model $savedForLater */
            $savedForLater = $e->getDeletedObject();
            $savedForLater->setDeletedAt(null);
            $this->saveForLaterMapper->save($savedForLater);
        } catch (BaseMapper\Exception $e) {
            // No record? Create one.
            $savedForLater = $this->saveForLaterMapper->createModel()->setUser($user)->setArticle($article);
            $this->saveForLaterMapper->save($savedForLater);
        }
    }


    /**
     * Removes the article from the user's 'saved-for-later' list
     *
     * Returns whether an item was removed, as opposed to never existing
     *
     * @param News\Model $article
     * @param User       $user
     * @return void
     */
    private function removeArticleFromUserSavedList(News\Model $article, User $user): void
    {
        $columnList = ['user_id' => $user->getId(), 'article_id' => $article->getId()];

        try {
            $savedForLater = $this->saveForLaterMapper->findOneByColumnList($columnList);
            $this->saveForLaterMapper->delete($savedForLater);
        } catch (BaseMapper\Exception $e) {
            // noop: the article isn't in the user's saved list
        }
    }
}
