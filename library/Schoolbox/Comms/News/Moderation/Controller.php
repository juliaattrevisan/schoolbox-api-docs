<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Moderation;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\Policy;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{

    /**
     * @var News\Mapper
     */
    private $newsMapper;

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
     * @param News\Mapper   $newsMapper
     * @param Policy\Mapper $policyMapper
     * @param ErrorMapper   $errorMapper
     */
    public function __construct(News\Mapper $newsMapper, Policy\Mapper $policyMapper, ErrorMapper $errorMapper)
    {
        $this->newsMapper = $newsMapper;
        $this->policyMapper = $policyMapper;
        $this->errorMapper = $errorMapper;
    }


    /**
     * Get a list of articles that need moderation
     *
     * @api GET /comms/news/moderation
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getList()
    {
        $lists = new Lists($this->newsMapper);
        $articles = new Collection();

        try {
            $policy = $this->policyMapper->findOneByColumn('identifier', 'global_moderation');
            $moderationRequired = (bool)$policy->getValue();

            $articles = $lists->getNeedModeration();
        } catch (MapperException $e) {
            $moderationRequired = false;
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/index.twig', [
            'articles' => $articles,
            'recentlyAcceptedArticles' => $lists->getRecentlyAccepted(),
            'recentlyRejectedArticles' => $lists->getRecentlyRejected(),
            'moderationRequired' => $moderationRequired
        ])->setTitle('Moderation List'));
    }


    /**
     * Submit For Moderation occurs here
     * A required action prior to Moderation taking place
     *
     * @api POST /comms/news/moderation/{articleId}/submit
     *
     * @param Model $article
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function postSubmit(Model $article)
    {
        if ((new News\ValidArticle())->isSatisfiedBy($article)) {
            $article->setSubmittedAt(\Alaress_Date_Format::now());

            /* If moderation isn't required, automatically accept self-moderation by the author
             */
            $policy = $this->policyMapper->findOneByColumn('identifier', 'global_moderation');
            $moderationRequired = (bool)$policy->getValue();

            try {
                if (!$moderationRequired) {
                    $article->setAcceptedBy($this->getUser())
                        ->setAcceptedAt(\Alaress_Date_Format::now());
                }
            } catch (MapperException $e) {
                // noop
            }

            $this->newsMapper->save($article);

            /* If you're a moderator and you authored your own article, redirect back to Detail
             * so you get immediate access to Publish
             *
             * If you're a content creator and you just submitted, redirect back to Detail so
             * you can see what you just submitted (because receipts are nice for anxious people)
             * TODO: Maybe it should do something more obvious to indicate this outcome though.
             */
            if ((new News\ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser())) {
                if ((new News\ValidUserIsAuthor())->isSatisfiedBy($this->getUser(), $article)) {
                    $nextStep = $this->getRouter()->generate('commsNews.getDetail', [ 'articleId' => $article->getId() ]);
                } else {

                    /* This represents a moderator submitting for (on behalf of) a content creator
                     * Normally you can't get here except by visiting a direct link and even that is
                     * questionable as to availability.
                     * TODO: SA-7040 Should moderators be able to access unsubmitted (draft) articles
                     */
                    $nextStep = $this->getRouter()->generate('commsNewsModeration.getList');
                }
            } else {
                $nextStep = $this->getRouter()->generate('commsNews.getDetail', [ 'articleId' => $article->getId() ]);
            }

            $this->getResponse()->redirect->to($nextStep);
        } else {

            /* Invalid article; save what you currently sent to us so you don't lose everything,
             * but warn you about it with a generic error.
             */
            $this->newsMapper->save($article);

            throw $this->errorMapper->generateException(ErrorMapper::ERROR_COMMS_NEWS_INVALID_ARTICLE);
        }
    }


    /**
     * Accept an article for publishing to its audience
     *
     * @api POST /comms/news/moderation/{articleId}/accept
     *
     * @param Model $article
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    public function postAccept(Model $article)
    {
        if ((new ValidUserCanAccept())->isSatisfiedBy($this->getUser(), $article)) {
            $now = \Alaress_Date_Format::now();

            /* Clear rejection reasons because they no longer matter
             */
            $article
                ->setRejectedAt(new \Alaress_Date_NullDate())
                ->setRejectedBy(null)
                ->setRejectedReason(null)
                ->setAcceptedAt($now)
                ->setAcceptedBy($this->getUser());

            /* If submitted_at was empty, fill them in because we're Approving directly
             * from an unsubmitted state
             */
            if (!$article->getSubmittedAt() instanceof User) {
                $article->setSubmittedAt($now);
            }

            $this->newsMapper->save($article);

            /* Go back to the list
             */
            $this->getResponse()->redirect->to($this->getRouter()
                                                    ->generate('commsNewsModeration.getList'));

            return;
        }

        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }


    /**
     * Reject an article
     *
     * This clears the submitted_at value, so authors can submit it again
     * This clears the accepted_at and _by value, to immediately hide from the world
     * This sets the rejected details
     *
     * Rejecting an article will send it back to the Compose/Preview phase, as if it had
     * never succeeded before lol (but it'll remember that you failed, and if stated, why)
     *
     * @api POST /comms/news/moderation/{articleId}/reject
     *
     * @param Model $article
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    public function postReject(Model $article)
    {
        if ((new ValidUserCanReject())->isSatisfiedBy($this->getUser(), $article)) {
            $article
                ->setSubmittedAt(null)
                ->setAcceptedAt(null)
                ->setAcceptedBy(null)
                ->setRejectedBy($this->getUser())
                ->setRejectedAt(\Alaress_Date_Format::now())
                ->setRejectedReason($this->getRequest()->post['rejectedReason']);

            $this->newsMapper->save($article);

            /* Redirect back to the moderation list
             */
            $nextStep = $this->getRouter()->generate('commsNewsModeration.getList');

            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true,
                    'redirect' => $nextStep
                ]));
            } else {
                $this->getResponse()->redirect->to($nextStep);
            }

            return;
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->status->set(400);
            $this->getResponse()->content->set($this->buildJsonOutput([
                'success' => false,
                'message' => $this->errorMapper->getMessageById(ErrorMapper::ERROR_PERM_PAGE_VIEW)
            ]));

            return;
        }

        throw $this->errorMapper->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
}
