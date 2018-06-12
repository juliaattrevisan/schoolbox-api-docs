<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News;


use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccessMapper;
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
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var Mapper
     */
    private $newsMapper;

    /**
     * Controller constructor.
     *
     * @param ErrorMapper      $errorMapper
     * @param UserMapper       $userMapper
     * @param UserAccessMapper $userAccessMapper
     * @param Mapper           $newsMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserMapper $userMapper, UserAccessMapper $userAccessMapper, Mapper $newsMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->newsMapper = $newsMapper;
    }

    /**
     * Provide form to create a news article
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getCreate()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'user' => $this->getUser()
        ])->setTitle('Comms News'));
    }


    /**
     * Create a news article
     *
     * @throws MapperException
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function postCreate()
    {
        /** @var Model $news */
        $news = $this->newsMapper->createModel();

        $this->postUpdate($news);
    }


    /**
     * Provide form to update a news article
     *
     * @param Model $news
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getUpdate(Model $news)
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'user' => $this->getUser(),
            'news' => $news
        ])->setTitle("Update {$news->getTitle()}"));
    }


    /**
     * Update a news article
     *
     * @param Model $news
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    public function postUpdate(Model $news)
    {
        $news
            ->setTitle($this->getRequest()->post['title'])
            ->setBody($this->getRequest()->post['body'])
            ->setBlurb($this->getRequest()->post['blurb'])
            ->setAuthor($this->getUser());

        if ((new ValidArticle())->isSatisfiedBy($news)) {
            $this->newsMapper->save($news);

            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('commsNews.getUpdate', ['newsId' => $news->getId()]));
            return;
        }

        /* TODO: Custom error message
         */
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }
}