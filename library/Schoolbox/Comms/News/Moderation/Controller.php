<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

use Alaress\Web\Controller\Base;
use Schoolbox\Collection;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Model;
use Schoolbox\Comms\News\Policy;
use Schoolbox\Mapper;

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

    /** @var News\Validation\Factory */
    private $validSpecFactory;


    /**
     * @param News\Mapper             $newsMapper
     * @param Policy\Mapper           $policyMapper
     * @param News\Validation\Factory $validSpecFactory
     */
    public function __construct(News\Mapper $newsMapper, Policy\Mapper $policyMapper, News\Validation\Factory $validSpecFactory)
    {
        $this->newsMapper       = $newsMapper;
        $this->policyMapper     = $policyMapper;
        $this->validSpecFactory = $validSpecFactory;
    }


    /**
     * Get a list of articles that need moderation
     *
     * @api GET /comms/news/moderation
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getList(): void
    {
        $lists = new Lists($this->newsMapper);
        $articles = new Collection();

        try {
            $policy = $this->policyMapper->findOneByColumn('identifier', 'global_moderation');
            $moderationRequired = (bool)$policy->getValue();

            $articles = $lists->getNeedModeration();
        } catch (Mapper\Exception $e) {
            $moderationRequired = false;
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/index.twig', [
            'articles' => $articles,
            'recentlyAcceptedArticles' => $lists->getRecentlyAccepted(),
            'recentlyRejectedArticles' => $lists->getRecentlyRejected(),
            'moderationRequired' => $moderationRequired,

            // Vars for the nav sidebar
            'page' => $this->getRequest()->query['page'],
            'moderationCount' => $lists->getNeedModerationCount(),
            'authoredCounts' => [
                'Drafted' => [
                    'count' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateDraft($this->getUser())),
                    'labelClass' => 'unsubmitted'
                ],
                'Awaiting Moderation' => [
                    'count' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateAwaitingModeration($this->getUser())),
                    'labelClass' => 'submitted'
                ],
                'Rejected' => [
                    'count' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateRejected($this->getUser())),
                    'labelClass' => 'submitted-late'
                ],
            ]
        ])->setTitle('Requiring Moderation'));
    }


    /**
     * Submit For Moderation occurs here
     * A required action prior to Moderation taking place
     *
     * Does not automatically approve an article if session user can moderate.
     * @see $this->postApprove
     *
     * @api POST /comms/news/moderation/{articleId}/submit
     *
     * @param Model $article
     * @throws Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws News\Exceptions\InvalidTransitionException
     */
    public function postSubmit(Model $article): void
    {
        $spec        = $this->validSpecFactory->buildSpecCanBeSubmitted($this->getUser());
        $unsatisfied = $spec->remainderUnsatisfiedBy($article);

        if ($unsatisfied) {
            $this->getResponse()->status->set(400);
            $this->getResponse()->content->set($this->buildJsonOutput([
                'success' => false,
                'message' => "Article appears to be invalid",
            ]));
            return;
        }

        // If someone else got in first, we don't care. No risk of data loss.
        if (!$article->isSubmitted()) {
            $article->submit();
            $this->newsMapper->save($article);
        }

        // TODO. The client should be responsible for determining what to do after submission
        // TODO. It's not for the controller to guess whether this route was invoked from a list, a component, a page etc.
        // TODO. THis should just return the outcome of the request, not a redirect.
        $route = $this->getRouter()->generate('commsNews.getDetail', [ 'articleId' => $article->getId() ]);
        $this->getResponse()->redirect->to($route);
    }


    /**
     * Approve an article for publishing to its audience
     *
     * @api POST /comms/news/moderation/{articleId}/accept
     *
     * @param Model $article
     * @throws Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws News\Exceptions\InvalidTransitionException
     */
    public function postApprove(Model $article): void
    {
        // If someone else got in first, we don't care. No risk of data loss.
        // It does imply the 'approvedBy' will remain as the first-in user
        if (!$article->isApproved()) {
            $article->approve($this->getUser());
            $this->newsMapper->save($article);
        }

        // TODO. The client should be responsible for determining what to do after approval.
        // TODO. It's not for the controller to guess whether this route was invoked from a list, a component, a page etc.
        // TODO. THis should just return the outcome of the request, not a redirect.
        $route = $this->getRouter()->generate('commsNewsModeration.getList');
        $this->getResponse()->redirect->to($route);
    }


    /**
     * Reject an article
     *
     *
     * Rejecting an article will send it back to the Compose/Preview phase, as if it had
     * never succeeded before lol (but it'll remember that you failed, and if stated, why)
     *
     * @api POST /comms/news/moderation/{articleId}/reject
     *
     * @param Model $article
     * @throws Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws News\Exceptions\InvalidTransitionException
     * @todo catch and handle InvalidTransitionException
     */
    public function postReject(Model $article): void
    {
        $responseData    = ['success' => true];
        $rejectionReason = $this->getRequest()->post['rejectedReason'];

        // Note that if article is already rejected, but the reason given is the same, we don't care
        // and just report a success. The implication is the rejectingUser will remain as the first-in user.

        if (!$article->isRejected()) {
            // Normal Case
            $article->reject($this->getUser(), $rejectionReason);
            $this->newsMapper->save($article);
            $responseData['success'] = true;
        } elseif ($article->getRejectedReason() !== $rejectionReason) {
            // If the article is already rejected then
            // we will lose either the original reason, or the new reason.
            // We need to warn the client so it can decide what to do.
            /** @noinspection NullPointerExceptionInspection */
            $message = "Article was already rejected by {$article->getRejectedBy()->getFullName()} at {$article->getRejectedAt()->getLocalShort()}";

            $this->getResponse()->status->set(400);
            $responseData['success'] = false;
            $responseData['message'] = $message;
        } else {
            // Already rejected but same message
            $responseData['message'] = 'Already rejected but reason is not different.';
        }

        if($responseData['success']){
            // TODO. The client should be responsible for determining what to do after rejection.
            // TODO. It's not for the controller to guess whether this route was invoked from a list, a component, a page etc.
            // TODO. THis should just return the outcome of the request, not a redirect.
            $responseData['redirect'] = $this->getRouter()->generate('commsNewsModeration.getList');
        }

        $this->getResponse()->content->set($this->buildJsonOutput($responseData));
    }
}
