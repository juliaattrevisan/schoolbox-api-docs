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
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\UserMapper;
use Schoolbox\Comms\News\Policy;


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
     * @var Policy\Mapper
     */
    private $policyMapper;

    /**
     * Controller constructor.
     *
     * @param ErrorMapper      $errorMapper
     * @param UserMapper       $userMapper
     * @param UserAccessMapper $userAccessMapper
     * @param Mapper           $newsMapper
     * @param Policy\Mapper    $policyMapper
     */
    public function __construct(ErrorMapper $errorMapper, UserMapper $userMapper, UserAccessMapper $userAccessMapper, Mapper $newsMapper, Policy\Mapper $policyMapper)
    {
        $this->errorMapper = $errorMapper;
        $this->userMapper = $userMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->newsMapper = $newsMapper;
        $this->policyMapper = $policyMapper;
    }


    /**
     * Provide form to create a news article
     *
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \UnexpectedValueException
     */
    public function getCreate()
    {
        /* Moderation policy
         */
        try {
            $moderationRequired = $this->policyMapper->findOneByColumn('identifier', 'global_moderation')->getValue();
        }
        catch (Exception $e) {
            $moderationRequired = false;
        }

        /* Feature image
         */
        $zone = new Zone('featureImage');
        $zone
            ->setMulti(false)
            ->setAttachPrompt('Attach a feature image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*');

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'action' => 'Create',
            'moderationRequired' => $moderationRequired,
            'user' => $this->getUser(),
            'featureImageZone' => $zone->getTwigContext(),
        ])->setTitle("Create {$this->getDi()->get('sitePage')->getLocalisedTerm('news', false, true)}"));
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
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \UnexpectedValueException
     */
    public function getUpdate(Model $news)
    {
        /* Moderation policy
         */
        try {
            $moderationRequired = $this->policyMapper->findOneByColumn('identifier', 'global_moderation')->getValue();
        }
        catch (Exception $e) {
            $moderationRequired = false;
        }

        /* Feature image
         */
        $zone = new Zone('featureImage');
        $zone
            ->setMulti(false)
            ->setAttachPrompt('Attach a feature image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*');

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'action' => 'Update',
            'moderationRequired' => $moderationRequired,
            'user' => $this->getUser(),
            'news' => $news,
            'featureImageZone' => $zone->getTwigContext(),
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
        if ($this->persist($news, $this->getRequest()->post)) {
            $this->getResponse()->redirect->afterPost($this->getRouter()->generate('commsNews.getUpdate', ['newsId' => $news->getId()]));
            return;
        }

        /* TODO: Custom error message
         */
        throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
    }


    /**
     * Persist model to db during news article authorship
     *
     * @param Model $news
     * @param array|\Aura\Web\Request\Values $data
     * @return bool
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function persist(Model $news, $data)
    {
        $news
            ->setTitle($data['title'])
            ->setBody($data['body'])
            ->setBlurb($data['blurb'])
            ->setAuthor($this->getUser())
            ->setModeratedBy(null)
            ->setModeratedAt(new \Alaress_Date_NullDate())
            ->setPublishAt(\Alaress_Date_Format::newLocal($data['publishAt']))
            ->setExpiresAt(\Alaress_Date_Format::newLocal($data['expiresAt']));

        if ((new ValidArticle())->isSatisfiedBy($news)) {
            $this->newsMapper->save($news);
            return true;
        }

        return false;
    }
}