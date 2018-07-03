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
use Schoolbox\Comms\News\ValidUserIsCommsOfficer;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
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
     * @var UserMapper
     */
    private $userMapper;

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
     * @param UserMapper    $userMapper
     */
    public function __construct(News\Mapper $newsMapper, Policy\Mapper $policyMapper, ErrorMapper $errorMapper, UserMapper $userMapper)
    {
        $this->newsMapper = $newsMapper;
        $this->policyMapper = $policyMapper;
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Get a list of articles that need moderation
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getList()
    {
        $lists = new Lists($this->newsMapper, $this->userMapper);
        $newsArticles = new Collection();

        try {
            $policy = $this->policyMapper->findOneByColumn('identifier', 'global_moderation');

            $moderationRequired = (bool)$policy->getValue();

            $newsArticles = $lists->getNeedModeration();
        } catch (MapperException $e) {
            $moderationRequired = false;
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/index.twig', [
            'newsArticles' => $newsArticles,
            'recentArticles' => $lists->getRecentlyAccepted(),
            'moderationRequired' => $moderationRequired
        ])->setTitle('Example'));
    }


    /**
     * Submit For Moderation occurs here
     * A required action prior to Moderation taking place
     *
     * @param Model $news
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function postSubmit(Model $news)
    {
        if ((new News\ValidArticle())->isSatisfiedBy($news)) {
            $news->setSubmittedAt(\Alaress_Date_Format::now());
            $this->newsMapper->save($news);

            /* If you're a moderator, go back to the Moderation list
             *
             * TODO: Otherwise currently recycle the same screen?
             */
            if ((new ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser())) {
                $nextStep = $this->getRouter()->generate('commsNewsModeration.getList');
            } else {
                $nextStep = $this->getRouter()->generate('commsNews.getDetail', [ 'newsId' => $news->getId() ]);
            }

            $this->getResponse()->redirect->to($nextStep);
        } else {

            /* Invalid article; save what you currently sent to us so you don't lose everything,
             * but warn you about it with a generic error.
             */
            $this->newsMapper->save($news);

            throw $this->errorMapper->generateException(ErrorMapper::ERROR_COMMS_NEWS_INVALID_ARTICLE);
        }
    }


    /**
     * Accept an article for publishing to its audience
     *
     * @param Model $news
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postAccept(Model $news)
    {
        $policy = $this->policyMapper->findOneByColumn('identifier', 'global_moderation');

        $moderationRequired = (bool)$policy->getValue();

        $news
            ->setAcceptedAt(\Alaress_Date_Format::now())
            ->setAcceptedBy($this->getUser());

        if ((new News\ValidBroadcast())->isSatisfiedBy($news, $moderationRequired)) {
            $this->newsMapper->save($news);
        }

        /* Return to Moderation Queue
         */
        $nextStep = $this->getRouter()->generate('commsNewsModeration.getList');
        $this->getResponse()->redirect->to($nextStep);
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
     * @param Model $news
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    public function postReject(Model $news)
    {
        if ((new ValidUserCanReject())->isSatisfiedBy($this->getUser())) {
            $news
                ->setSubmittedAt(null)
                ->setAcceptedAt(null)
                ->setAcceptedBy(null)
                ->setRejectedBy($this->getUser())
                ->setRejectedAt(\Alaress_Date_Format::now())
                ->setRejectedReason($this->getRequest()->post['rejectedReason']);

            $this->newsMapper->save($news);

            /* Redirect back to the moderation list so you can deal with other articles
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
