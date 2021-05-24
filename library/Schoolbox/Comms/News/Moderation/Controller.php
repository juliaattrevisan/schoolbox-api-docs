<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Model;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;

class Controller extends Base
{

    /**
     * Flash message text appears in this key
     */
    public const ARTICLE_FLASH_MESSAGE_KEY = 'ArticleFlashMessage';

    /* Moderation API error codes:
     *
     * These ensure the client has sufficient information to know what to do. HTTP response codes are insufficient to distinguish some cases.
     * These are intentionally not tied to a specified route, the client can derive semantics from the combination
     * of the route and the abstract code. eg 'different attributes' and 'reject' implies a different rejection reason.
     *
     * Note that we need to consider concurrent modification. eg
     * 1. moderator loads page, sees valid article.
     * 2. Someone else changes to make invalid (eg removing title)
     * 3. moderator clicks 'approve'
     * Expected: System should reject the approval, but give the reason why. Eg suggest to refresh the page.
     *
     */
    private const ERROR_CODE_OK                                    = 0; // 200 all aspects of requests were fulfilled as expected
    private const ERROR_CODE_INVALID_ARTICLE                       = 1; // 400 article attributes invalid. Client validation should protect from most cases.
    private const ERROR_CODE_ALREADY_DONE_AND_SAME_ATTRIBUTES      = 1; // 200 someone else got in first, and no data loss if we ignore
    private const ERROR_CODE_ALREADY_DONE_BUT_DIFFERENT_ATTRIBUTES = 2; // 409 someone else got in first, but data loss if we ignore
    private const ERROR_CODE_INVALID_TRANSITION                    = 3; // 400 client is authorized, but is asking for the impossible.
    private const ERROR_CODE_UNABLE_TO_SAVE                        = 4; // 500 not the client's fault.

    /**
     * @var News\Mapper
     */
    private $newsMapper;

    /** @var News\Validation\Factory */
    private $validSpecFactory;


    /**
     * @param News\Mapper             $newsMapper
     * @param News\Validation\Factory $validSpecFactory
     */
    public function __construct(News\Mapper $newsMapper, News\Validation\Factory $validSpecFactory)
    {
        $this->newsMapper       = $newsMapper;
        $this->validSpecFactory = $validSpecFactory;
    }


    /**
     * Get a list of articles that need moderation
     *
     * @api GET /news/moderation
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getList(): void
    {
        $lists = new Lists($this->newsMapper);
        $sidebar = new News\Sidebar($this->newsMapper, $this->getUser(), $this->getRequest()->query['page']);

        $data = array_merge([
            'articles' => $this->newsMapper->findByPredicates($this->newsMapper->getPredicateAwaitingModeration()),
            'recentlyAcceptedArticles' => $lists->getRecentlyAccepted(),
            'recentlyRejectedArticles' => $lists->getRecentlyRejected(),
            'selected' => null,
        ], $sidebar->generate());


        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/index.twig', $data)->setTitle('Requiring Moderation')->setTab('news'));
    }

    /**
     * Set a flash message about submitting articles
     * Flash messages aren't needed in other contexts yet afaik
     *
     * @param Model $article
     */
    private function generateSubmitFlashMessage(Model $article)
    {
        $moderationRequired = $this->getConfigLoader()->get(ConfigMapper::_GLOBAL_MODERATION);
        $message = '';

        $notifsOn = true; // TODO actually assign this

        if (!$article->isExpired() && $moderationRequired) {
            if ($notifsOn) {
                $message = 'Your article has been submitted for moderation. You will receive a notification when your article is rejected or approved.';
            } else {
                $message = 'Your article has been submitted for moderation.';
            }
        }

        /* If a message is provided, let it be available in the Comms\News\Controller namespace */
        if ('' !== $message) {
            $segment = $this->getSession()->getSegment('Schoolbox\Comms\News\Controller');
            $segment->setFlash(self::ARTICLE_FLASH_MESSAGE_KEY, $message);
        }
    }


    /**
     * Submit For Moderation occurs here
     * A required action prior to Moderation taking place
     *
     * Does not automatically approve an article if session user can moderate.
     * @see $this->postApprove
     *
     * @api POST /news/moderation/{articleId}/submit
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
        if ($article->isAwaitingModeration()) {
            // Someone else got in first. That's fine. No need to warn user.
            $httpCode  = 200;
            $errorCode = self::ERROR_CODE_ALREADY_DONE_AND_SAME_ATTRIBUTES;
            $message   = 'Article was already submitted';
        } elseif (!$article->canBeSubmitted()) {
            // Invalid state transition
            $httpCode  = 400;
            $errorCode = self::ERROR_CODE_INVALID_TRANSITION;
            $message   = "Invalid transition. Current article state: {$article->getStatus()}";
        } elseif (!$this->validSpecFactory->buildSpecCanBeSubmitted($this->getUser())->isSatisfiedBy($article)) {
            // Invalid article. Warn user
            // (template + clients-side validation should prevent this case, but we still need to check)
            $httpCode  = 400;
            $errorCode = self::ERROR_CODE_INVALID_ARTICLE;
            $message   = 'Article appears to be invalid';
        } else {
            // Valid article. Attempt to submit and save
            $article->submit();
            if (false === $this->newsMapper->save($article)) {
                $httpCode  = 500;
                $errorCode = self::ERROR_CODE_UNABLE_TO_SAVE;
                $message   = 'Could not save article state';
            } else {
                $httpCode  = 200;
                $errorCode = self::ERROR_CODE_OK;
                $message   = 'Successfully submitted and saved';
            }
        }

        if (200 === $httpCode) {
            /* Set a flash message to tell people what they can expect to happen to their article next
             */
            $this->generateSubmitFlashMessage($article);

            // TODO. The client should be responsible for determining what to do after submission
            // TODO. It's not for the controller to guess whether this route was invoked from a list, a component, a page etc.
            // TODO. THis should just return the outcome of the request, not a redirect.
            $route = $this->getRouter()->generate('commsNews.getDetail', ['articleId' => $article->getId()]);
            if ($this->isXhrRequest()) {
                $this->buildAjaxResponse($httpCode, $errorCode, $message, $route);
            } else {
                $this->getResponse()->redirect->to($route);
            }
        } else {
            $this->buildAjaxResponse($httpCode, $errorCode, $message);
        }
    }


    /**
     * Approve an article for publishing to its audience
     *
     * @api POST /news/moderation/{articleId}/accept
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
        if ($article->isApproved()) {
            $httpCode  = 200;
            $errorCode = self::ERROR_CODE_ALREADY_DONE_AND_SAME_ATTRIBUTES;
            $message   = 'Article was already approved';
        } elseif (!$article->canBeApproved()) {
            // Invalid state transition
            $httpCode  = 400;
            $errorCode = self::ERROR_CODE_INVALID_TRANSITION;
            $message   = "Invalid transition. Current article state: {$article->getStatus()}";
        } elseif (!$article->isSubmitted() && !$this->validSpecFactory->buildSpecCanBeSubmitted($this->getUser())->isSatisfiedBy($article)) {
            // Invalid article. Warn user
            // (template + clients-side validation should prevent this case, but we still need to check)
            $httpCode  = 400;
            $errorCode = self::ERROR_CODE_INVALID_ARTICLE;
            $message   = 'Article appears to be invalid';
        } else {
            // Valid article. Attempt to approve and save
            $article->approve($this->getUser());
            if (false === $this->newsMapper->save($article)) {
                $httpCode  = 500;
                $errorCode = self::ERROR_CODE_UNABLE_TO_SAVE;
                $message   = 'Could not save article state';
            } else {
                $httpCode  = 200;
                $errorCode = self::ERROR_CODE_OK;
                $message   = 'Successfully approved and saved';
            }
        }

        if (200 === $httpCode) {
            // TODO. The client should be responsible for determining what to do after approval.
            // TODO. It's not for the controller to guess whether this route was invoked from a list, a component, a page etc.
            // TODO. THis should just return the outcome of the request, not a redirect.
            if ($article->isPublished() && $this->getRequest()->post->get(News\Querystring::RETURN_URL)) {
                $route = News\Querystring::decode($this->getRequest()->post->get(News\Querystring::RETURN_URL));
            } else {
                $listRoute = $this->getRouteIfAuthorized('commsNewsModeration.getList');
                if (
                    $listRoute
                    && (!$article->getAuthor() || ($article->getAuthor()->getId() !== $this->getUser()->getId()))
                ) {
                    $route = $listRoute;
                } else {
                    $route = $this->getRouter()->generate('commsNews.getDetail', ['articleId' => $article->getId()]);
                }
            }

            if ($this->isXhrRequest()) {
                $this->buildAjaxResponse($httpCode, $errorCode, $message, $route);
            } else {
                $this->getResponse()->redirect->to($route);
            }
        } else {
            $this->buildAjaxResponse($httpCode, $errorCode, $message);
        }
    }


    /**
     * Reject an article
     *
     *
     * Rejecting an article will send it back to the Compose/Preview phase, as if it had
     * never succeeded before lol (but it'll remember that you failed, and if stated, why)
     *
     * @api POST /news/moderation/{articleId}/reject
     *
     * @param Model $article
     * @throws Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws News\Exceptions\InvalidTransitionException
     */
    public function postReject(Model $article): void
    {
        $rejectionReason = $this->getRequest()->post['rejectedReason'];

        if ($article->isRejected()) {
            if ($article->getRejectedReason() === $rejectionReason) {
                // Already rejected for same reason. Just carry on.
                $httpCode  = 200;
                $errorCode = self::ERROR_CODE_ALREADY_DONE_AND_SAME_ATTRIBUTES;
                $message   = "Article was already rejected" . ($rejectionReason ? " for identical reasons" : '');
            } else {
                // If the article is already rejected then we will lose either the original reason, or the new reason.
                // We need to warn the client so it can decide what to do.
                $httpCode  = 409;
                $errorCode = self::ERROR_CODE_ALREADY_DONE_BUT_DIFFERENT_ATTRIBUTES;
                /** @noinspection NullPointerExceptionInspection */
                $message = "Article was already rejected by {$article->getRejectedBy()->getFullName()} at {$article->getRejectedAt()->getLocalShort()}";
            }
        } elseif (!$article->canBeRejected()) {
            // Invalid state transition
            $httpCode  = 400;
            $errorCode = self::ERROR_CODE_INVALID_TRANSITION;
            $message   = "Invalid transition. Current article state: {$article->getStatus()}";
        } else {
            // Valid article. Attempt to approve and save
            $article->reject($this->getUser(), $rejectionReason);
            if (false === $this->newsMapper->save($article)) {
                $httpCode  = 500;
                $errorCode = self::ERROR_CODE_UNABLE_TO_SAVE;
                $message   = 'Could not save article state';
            } else {
                $httpCode  = 200;
                $errorCode = self::ERROR_CODE_OK;
                $message   = 'Successfully rejected and saved';
            }
        }

        if (200 === $httpCode) {
            $returnUrl = News\Querystring::decode($this->getRequest()->query->get(News\Querystring::RETURN_URL));
            if ($returnUrl) {
                $route = $returnUrl;
            } else {
                $listRoute = $this->getRouteIfAuthorized('commsNewsModeration.getList');
                if ($listRoute) {
                    $route = $listRoute;
                } else {
                    $route = $this->getRouter()->generate('commsNews.getDetail', ['articleId' => $article->getId()]);
                }
            }
            $this->buildAjaxResponse($httpCode, $errorCode, $message, $route);
        } else {
            $this->buildAjaxResponse($httpCode, $errorCode, $message);
        }
    }


    /**
     * Build a consistently-formatted Moderation-API payload
     *
     * @param int         $httpCode
     * @param int         $errorCode
     * @param string|null $message
     * @param string|null $redirect
     */
    private function buildAjaxResponse(int $httpCode, int $errorCode, string $message = null, string $redirect = null): void
    {
        $data = ['errorCode' => $errorCode,];

        if (null !== $message) {
            $data['message'] = $message;
        }
        if (null !== $redirect) {
            $data['redirect'] = $redirect;
        }

        $this->getResponse()->status->set($httpCode);
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }
}
