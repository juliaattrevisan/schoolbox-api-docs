<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Moderation;

use Alaress\Database\Predicate\Builder;
use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News\Model;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Base
{
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
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserMapper $userMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
    }

    /**
     * Get a list of articles that need moderation
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getList()
    {
        try {
            $policy = \Schoolbox\Comms\News\Policy\Mapper::getInstance()->findOneByColumn('identifier', 'global_moderation');

            $moderationRequired = (bool)$policy->getValue();
        }
        catch (MapperException $e) {
            $moderationRequired = false;
        }

        $newsArticles = [];

        if ($moderationRequired) {
            $newsArticles = \Schoolbox\Comms\News\Mapper::getInstance()->findByColumn('moderated_at', null)->filter(
                function (Model $item) {
                    return !$item->getSubmittedAt()->isNull();
                }
            );
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/index.twig', [
            'newsArticles' => $newsArticles,
            'moderationRequired' => $moderationRequired
        ])->setTitle('Example'));
    }


    /**
     * Load a news article so you can read its contents (the detail screen)
     *
     * @param Model $news
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getDetail(Model $news)
    {
        $policies = \Schoolbox\Comms\News\Policy\Mapper::getInstance()->findAll()->indexBy('identifier');

        /* The only known redirector that obeys a convention
         */
        $returnUrl = Redirect::encode(
            $this->getRouter()->generate('commsNewsModeration.getDetail', ['newsId' => $news->getId()])
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/detail.twig', [
            'policies' => $policies,
            'news' => $news,
            'returnUrl' => $returnUrl
        ])->setTitle($news->getTitle()));
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
        $news
            ->setModeratedAt(\Alaress_Date_Format::now())
            ->setModeratedBy($this->getUser());

        \Schoolbox\Comms\News\Mapper::getInstance()->save($news);

        /* Return to Moderation Queue
         */
        $nextStep = $this->getRouter()->generate('commsNewsModeration.getList');
        $this->getResponse()->redirect->to($nextStep);
    }

    /**
     * Process the form vars
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function postExample()
    {
        try {
            $user = $this->userMapper->findById($this->getRequest()->post['id']);
        }
        catch (MapperException $e) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/submit.twig', [
            'user' => $user,
            'var1' => $this->getRequest()->post['var1']
        ])->setTitle('Example Form Output'));
    }
}