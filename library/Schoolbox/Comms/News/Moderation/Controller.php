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
use Schoolbox\Comms\News\Policy;
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
        }
        catch (MapperException $e) {
            $moderationRequired = false;
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/index.twig', [
            'newsArticles' => $newsArticles,
            'recentArticles' => $lists->getRecentlyModerated(),
            'moderationRequired' => $moderationRequired
        ])->setTitle('Example'));
    }


    /**
     * Accept an article for publishing to its audience
     *
     * @param News\Model $news
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function postAccept(News\Model $news)
    {
        $policy = $this->policyMapper->findOneByColumn('identifier', 'global_moderation');

        $moderationRequired = (bool)$policy->getValue();

        $news
            ->setModeratedAt(\Alaress_Date_Format::now())
            ->setModeratedBy($this->getUser());

        if ((new News\ValidBroadcast())->isSatisfiedBy($news, $moderationRequired)) {
            $this->newsMapper->save($news);
        }

        /* Return to Moderation Queue
         */
        $nextStep = $this->getRouter()->generate('commsNewsModeration.getList');
        $this->getResponse()->redirect->to($nextStep);
    }
}