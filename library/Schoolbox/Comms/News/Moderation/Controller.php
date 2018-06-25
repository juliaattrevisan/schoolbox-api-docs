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
use Schoolbox\Comms\News\ValidBroadcast;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
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
     * @throws \Alaress\Database\Predicate\InvalidColumnException
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

        /* A list of submitted articles that require moderation
         */
        $newsArticles = [];

        if ($moderationRequired) {
            $unmoderatedArticles = (new Builder($this->userMapper->getDatabase(), 'comms_news_article'))
                ->notNull('submitted_at')
                ->isNull('moderated_at');

            $newsArticles = \Schoolbox\Comms\News\Mapper::getInstance()->findByPredicates($unmoderatedArticles);
        }

        /* A list of recently-modified articles
         * Otherwise it's pretty disorienting not knowing what you just did
         */
        $recentlyModerated = (new Builder($this->userMapper->getDatabase(), 'comms_news_article'))
            ->notNull('moderated_at');

        $recentArticles = \Schoolbox\Comms\News\Mapper::getInstance()->findByPredicates($recentlyModerated);

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNewsModeration/index.twig', [
            'newsArticles' => $newsArticles,
            'recentArticles' => $recentArticles,
            'moderationRequired' => $moderationRequired
        ])->setTitle('Example'));
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
        $policy = \Schoolbox\Comms\News\Policy\Mapper::getInstance()->findOneByColumn('identifier', 'global_moderation');

        $moderationRequired = (bool)$policy->getValue();

        $news
            ->setModeratedAt(\Alaress_Date_Format::now())
            ->setModeratedBy($this->getUser());

        if ((new ValidBroadcast())->isSatisfiedBy($news, $moderationRequired)) {
            \Schoolbox\Comms\News\Mapper::getInstance()->save($news);
        }

        /* Return to Moderation Queue
         */
        $nextStep = $this->getRouter()->generate('commsNewsModeration.getList');
        $this->getResponse()->redirect->to($nextStep);
    }
}