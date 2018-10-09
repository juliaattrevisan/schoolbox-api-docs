<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News\Feed;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\News\TopicMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * @var News\Mapper
     */
    private $newsMapper;

    /**
     * @var TopicMapper
     */
    private $topicMapper;

    /**
     * @var News\SaveForLater\Mapper
     */
    private $saveForLaterMapper;

    /**
     * @var News\Statistics\Mapper
     */
    private $statisticsMapper;

    /**
     * @var News\Statistics\Cumulative\Mapper
     */
    private $statisticsCumulativeMapper;

    /**
     * @var IConnector
     */
    private $databaseInternal;

    /** @var ArticleResolver */
    private $articleResolver;


    /**
     * Controller constructor.
     *
     * @param News\Mapper                       $newsMapper
     * @param TopicMapper                       $topicMapper
     * @param News\SaveForLater\Mapper          $saveForLaterMapper
     * @param News\Statistics\Mapper            $statisticsMapper
     * @param News\Statistics\Cumulative\Mapper $statisticsCumulativeMapper
     * @param IConnector                        $databaseInternal
     * @param ArticleResolver                   $articleResolver
     */
    public function __construct(
        News\Mapper $newsMapper,
        TopicMapper $topicMapper,
        News\SaveForLater\Mapper $saveForLaterMapper,
        News\Statistics\Mapper $statisticsMapper,
        News\Statistics\Cumulative\Mapper $statisticsCumulativeMapper,
        IConnector $databaseInternal,
        ArticleResolver $articleResolver
    ) {
        $this->newsMapper                 = $newsMapper;
        $this->topicMapper                = $topicMapper;
        $this->saveForLaterMapper         = $saveForLaterMapper;
        $this->statisticsMapper           = $statisticsMapper;
        $this->statisticsCumulativeMapper = $statisticsCumulativeMapper;
        $this->databaseInternal           = $databaseInternal;
        $this->articleResolver            = $articleResolver;
    }


    /**
     * Generate RSS feed containing articles audienced to $user
     * and optionally also audienced to $folder
     *
     * An optional ?topic= can be used to filter this
     *
     * @api /news/feed/{key}{/folderId}
     *
     * @param User              $user
     * @param null|Folder\Model $folder
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Web\Exception\InvalidComponent
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \OutOfRangeException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getFeed(User $user, ?Folder\Model $folder)
    {

        /* This is only used for paginating the article set but the rss feed has no pagination
         */
        $urlPrefix = '';

        /* Manufacture params
         * Compact Mode, Page Number, Hide Paginator aren't used here
         */
        $args = [];

        /* If a topic slug exists, use it
         * Otherwise, get articles across all topics
         */
        $args[News\Querystring::TOPIC_SLUG] = News\Mapper::TOPIC_ALL_SLUG;

        $slug = $this->getRequest()->query[News\Querystring::TOPIC_SLUG];

        if (\strlen($slug)) {
            $args[News\Querystring::TOPIC_SLUG] = $slug;
        }

        /* No limit to the number of articles
         */
        $args[News\Querystring::LIMIT] = 0;

        /* If a folder was specified, get the feed for that folder
         */
        if (null !== $folder) {
            $args[News\Querystring::FOLDER] = $folder->getId();
        }

        $params = new News\Querystring($args);

        /* Get all the articles across all topics
         */
        $topicData = (new News\Lists(
            (int)$this->getConfigLoader()->get('search_type_limit'),
            $this->newsMapper,
            $this->getConfigLoader()->get(ConfigMapper::_READ_MORE),
            $this->getConfigLoader()->get(ConfigMapper::_SAVE_FOR_LATER),
            $this->saveForLaterMapper,
            $this->statisticsMapper,
            $this->statisticsCumulativeMapper,
            $this->databaseInternal,
            $this->articleResolver
        ))->findForTopic(
            $urlPrefix,
            $params,
            $user,
            $this->topicMapper->findAll(['order'=>['sequence'=>'ASC']]),
            null,
            null
        );


        /* I haven't seen an outputter for xml in Web/Output
         */
        $this->getResponse()->content->set($this->buildXmlOutput('@commsNewsFeed/rss.twig', [
            'folder' => $folder,
            'title' => $this->getConfigLoader()->get('title'),
            'schoolName' => $this->getConfigLoader()->get('school_name'),
            'baseUrl' => $this->getRequest()->url->get(PHP_URL_SCHEME) . '://' . $this->getRequest()->url->get(PHP_URL_HOST),
            'url' => $this->getRequest()->url->get(),
            'articles' => $topicData['articles']
        ]));
    }
}
