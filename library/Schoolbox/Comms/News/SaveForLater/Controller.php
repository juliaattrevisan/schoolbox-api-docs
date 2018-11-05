<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\SaveForLater;

use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper as BaseMapper;
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
     * Controller constructor.
     * @param Mapper $saveForLaterMapper
     * @param News\Mapper $newsMapper
     */
    public function __construct(Mapper $saveForLaterMapper, News\Mapper $newsMapper)
    {
        $this->saveForLaterMapper = $saveForLaterMapper;
        $this->newsMapper         = $newsMapper;
    }


    /**
     * A list of articles that you saved for later
     *
     * @api GET /news/saved
     *
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getList(): void
    {
        $articles = (new Lists($this->saveForLaterMapper, $this->newsMapper))->findForUser($this->getUser()->getId());
        $params = new News\Querystring($this->getRequest()->query);
        $params->setReferrer(News\Statistics\Mapper::REFERRER_SAVED);

        if ( $this->isXhrRequest() ) {
            $this->getResponse()->content->set($this->buildJsonOutput($articles->getPropertyValues('id')));
            return;
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsSaveForLater/index.twig', [
            'articles' => $articles,
            'referrer' => $params->getReferrer(),

            // Vars for the nav sidebar
            'page' => $this->getRequest()->query['page'],
            'moderationCount' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateAwaitingModeration()),
            'authoredCounts' => [
                'Drafted' => [
                    'count' => $this->newsMapper->countByPredicates(
                        $this->newsMapper
                            ->getPredicateDraft()
                            ->equals('author_id', $this->getUser()->getId())
                    ),
                    'labelClass' => 'unsubmitted'
                ],
                'Awaiting Moderation' => [
                    'count'      => $this->newsMapper->countByPredicates(
                        $this->newsMapper
                            ->getPredicateAwaitingModeration()
                            ->equals('author_id', $this->getUser()->getId())
                    ),
                    'labelClass' => 'submitted',
                ],
                'Rejected' => [
                    'count'      => $this->newsMapper->countByPredicates(
                        $this->newsMapper
                            ->getPredicateRejected()
                            ->equals('author_id', $this->getUser()->getId())
                    ),
                    'labelClass' => 'submitted-late',
                ],
            ],
            'readMore' => $this->getConfigLoader()->get(ConfigMapper::_READ_MORE),
        ])->setTitle('Saved For Later'));
    }

    /**
     * Get a list of IDs of articles that have been saved for later
     *
     * @return array|int[]
     */
    private function getSavedArticleIds() : array
    {
        return (new Lists($this->saveForLaterMapper, $this->newsMapper))->findForUser($this->getUser()->getId())->getPropertyValues('id');
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
        } catch (BaseMapper\Exception|PDOException|UnexpectedValueException $e) {
            // Server-side error, not the client's fault. Developers to be alerted.
            $this->captureException($e);
            $this->getResponse()->status->set(500);
        }

        $savedArticles = $this->getSavedArticleIds();
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput($savedArticles));
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

        $savedArticles = $this->getSavedArticleIds();
        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput($savedArticles));
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
     * @return bool
     * @throws BaseMapper\Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function addArticleToUserSavedList(News\Model $article, User $user): bool
    {
        $columnList = ['user_id' => $user->getId(), 'article_id' => $article->getId()];

        try {
            $savedForLater = $this->saveForLaterMapper->findOneByColumnList($columnList, [], ['deleted' => true]);
        } catch (BaseMapper\Exception $e) {
            // No record? Create one.
            $savedForLater = $this->saveForLaterMapper->createModel()->setUser($user)->setArticle($article);
            $this->saveForLaterMapper->save($savedForLater);
            return true;
        }

        // Was in list, but was removed. Treated as an update action, not a restore action.
        if ($savedForLater->isDeleted()) {
            $savedForLater->setDeletedAt(null);
            $this->saveForLaterMapper->save($savedForLater);
            return true;
        }
        return false;
    }


    /**
     * Removes the article from the user's 'saved-for-later' list
     *
     * Returns whether an item was removed, as opposed to never existing
     *
     * @param News\Model $article
     * @param User       $user
     * @return bool
     * @throws BaseMapper\Exception
     */
    private function removeArticleFromUserSavedList(News\Model $article, User $user): bool
    {
        $columnList = ['user_id' => $user->getId(), 'article_id' => $article->getId()];

        try {
            $savedForLater = $this->saveForLaterMapper->findOneByColumnList($columnList, [], ['deleted' => true]);
        } catch (BaseMapper\Exception $e) {
            return false;
        }

        if ($savedForLater->isDeleted()) {
            return false;
        }
        return $this->saveForLaterMapper->delete($savedForLater);
    }
}
