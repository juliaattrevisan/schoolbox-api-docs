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
use Schoolbox\Comms\News\Policy;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception;

class Controller extends Base
{

    /**
     * Representations of when an article is in your Saved For Later list
     */
    private const SAVED = 1;
    private const UNSAVED = 0;

    /**
     * @var Mapper
     */
    private $saveForLaterMapper;

    /**
     * @var Policy\Mapper
     */
    private $policyMapper;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;


    /**
     * Controller constructor.
     *
     * @param Mapper        $saveForLaterMapper
     * @param Policy\Mapper $policyMapper
     * @param ErrorMapper   $errorMapper
     */
    public function __construct(Mapper $saveForLaterMapper, Policy\Mapper $policyMapper, ErrorMapper $errorMapper)
    {
        $this->saveForLaterMapper = $saveForLaterMapper;
        $this->policyMapper = $policyMapper;
        $this->errorMapper = $errorMapper;
    }


    /**
     * Provide form
     *
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getList()
    {
        $articles = $this->saveForLaterMapper->findForUser($this->getUser()->getId());

        try {
            $policy = $this->policyMapper->findOneByColumn('identifier', 'save_for_later');

            $canSaveForLater = (bool)$policy->getValue();
        } catch (Exception $e) {
            $canSaveForLater = false;
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsSaveForLater/index.twig', [
            'articles' => $articles,
            'canSaveForLater' => $canSaveForLater
        ])->setTitle('Saved For Later'));
    }


    /**
     * Toggle the current state of this article in a user's Saved For Later list
     * You never really just activate them on their own - it's a toggle of states
     * and it happens asynchronously
     *
     * @param News\Model $article
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function postToggle(News\Model $article)
    {
        if ($this->getRequest()->isXhr()) {
            $outcome = $this->toggle($article);

            if (!$outcome['success']) {
                $this->getResponse()->status->set(400);
            }

            $this->getResponse()->content->set($this->buildJsonOutput($outcome));

            return;
        }
    }


    /**
     * @param News\Model $article
     * @return array
     */
    private function toggle(News\Model $article): array
    {
        /* Can only save when the Policy is on
         */
        try {
            $policy = $this->policyMapper->findOneByColumn('identifier', 'save_for_later');

            $canSaveForLater = (bool)$policy->getValue();

            if (!$canSaveForLater) {
                return [
                    'success' => false,
                    'message' => 'This feature has been disabled.'
                ];
            }
        } catch (Exception $e) {

            /* Unknown settings; just leave
             */
            return [
                'success' => false,
                'message' => 'Could not save item.'
            ];
        }

        /* Policy lets you do a thing
         */
        try {
            $this->saveForLaterMapper->findOneByColumnList([
                'user_id' => $this->getUser()->getId(),
                'article_id' => $article->getId()
            ]);

            $exists = true;
        } catch (Exception $e) {
            $exists = false;
        }

        /* It's already in your list; remove it
         */
        if ($exists) {
            $this->saveForLaterMapper->deleteByColumnList([
                'user_id' => $this->getUser()->getId(),
                'article_id' => $article->getId()
            ]);

            return [
                'success' => true,
                'currentValue' => self::UNSAVED
            ];
        }

        /* Only save a new one if it doesn't already exist
         */
        try {
            $record = $this->saveForLaterMapper->createModel()
                ->setUser($this->getUser())
                ->setArticle($article);

            $this->saveForLaterMapper->save($record);

            return [
                'success' => true,
                'currentValue' => self::SAVED
            ];
        } catch (\PDOException|\UnexpectedValueException|Exception $e) {
            return [
                'success' => false,
                'message' => 'Could not save item.'
            ];
        }
    }


    /**
     * Remove an item from your Saved list
     *
     * @throws \Aura\Router\Exception\RouteNotFound
     * @param News\Model $article
     */
    public function postRemove(News\Model $article)
    {
        if ($this->getRequest()->isXhr()) {
            $this->saveForLaterMapper->deleteByColumnList([
                'user_id' => $this->getUser()->getId(),
                'article_id' => $article->getId()
            ]);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'success' => true,
                'nextUrl' => $this->getRouter()->generate('commsNewsSaveForLater.postRemove', ['newsId' => $news->getId()])
            ]));
        }
    }
}
