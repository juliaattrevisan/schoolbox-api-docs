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
use Aura\Router\Exception\RouteNotFound;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Policy;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\Topic;
use Schoolbox\News\TopicMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;

class Controller extends Base
{

    /**
     * Number of news articles per page
     * TODO: This is a config var
     */
    private const ARTICLES_PER_PAGE = 1;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * @var Mapper
     */
    private $newsMapper;

    /**
     * @var Policy\Mapper
     */
    private $policyMapper;

    /**
     * @var TopicMapper
     */
    private $topicMapper;

    /**
     * @var SaveForLater\Mapper
     */
    private $saveForLaterMapper;

    /**
     * Controller constructor.
     *
     * @param ErrorMapper         $errorMapper
     * @param Mapper              $newsMapper
     * @param Policy\Mapper       $policyMapper
     * @param TopicMapper         $topicMapper
     * @param SaveForLater\Mapper $saveForLaterMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        Mapper $newsMapper,
        Policy\Mapper $policyMapper,
        TopicMapper $topicMapper,
        SaveForLater\Mapper $saveForLaterMapper
    ) {
        $this->errorMapper = $errorMapper;
        $this->newsMapper = $newsMapper;
        $this->policyMapper = $policyMapper;
        $this->topicMapper = $topicMapper;
        $this->saveForLaterMapper = $saveForLaterMapper;
    }


    /**
     * Provide form to create a news article
     *
     * @throws MapperException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \UnexpectedValueException
     */
    public function getCreate()
    {
        $policies = $this->policyMapper->findAll()->indexBy('identifier');

        /* Spawn an empty news with default settings
         */
        /** @var Model $article */
        $article = $this->newsMapper->createModel()
            ->setPublishAt(\Alaress_Date_Format::now())
            ->setExpiresAt(\Alaress_Date_Format::now());

        /* Policies: Publish Date Offset
         */
        $policy = $policies->getOffset('publish_date_offset');

        if ((new ValidDateOffset())->isSatisfiedBy($policy)) {
            $article->getPublishAt()->modify($policy->getValue())->endOfDay();
        }

        /* Policies: Expiry Date Offset
         */
        $policy = $policies->getOffset('expiry_date_offset');

        if ((new ValidDateOffset())->isSatisfiedBy($policy)) {
            $article->getExpiresAt()->modify($policy->getValue())->endOfDay();
        }

        /* Feature image
         */
        $zone = (new Zone('featureImage'))
            ->setMulti(false)
            ->setAttachPrompt('Attach a feature image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*');

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'policies' => $policies,
            'action' => 'Create',
            'user' => $this->getUser(),
            'article' => $article,
            'featureImageZone' => $zone->getTwigContext(),
        ])->setTitle("Create {$this->getDi()->get('sitePage')->getLocalisedTerm('news', false, true)}"));
    }


    /**
     * Create a news article
     *
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     */
    public function postCreate()
    {
        /* Set YOU as the initial author
         * Otherwise, when a moderator edits your article, they might assume direct authorship instead
         */
        $article = $this->newsMapper->createModel()->setAuthor($this->getUser());

        $this->postUpdate($article);
    }


    /**
     * Provide form to update a news article
     *
     * @param Model $article
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getUpdate(Model $article)
    {
        $policies = $this->policyMapper->findAll()->indexBy('identifier');

        $moderationRequired = $policies->getOffset('global_moderation')->getValue();

        /* Only comms officers can edit articles that have been accepted for publishing
         */
        if (!(new ValidUserCanEdit())->isSatisfiedBy($this->getUser(), $moderationRequired, $article)) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_COMMS_NEWS_ALREADY_ACCEPTED);
        }

        /* Feature image
         */
        $zone = (new Zone('featureImage'))
            ->setMulti(false)
            ->setAttachPrompt('Attach a feature image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*');

        if ($article->getFeatureImage()) {
            $zone->attachFiles([$article->getFeatureImage()->getHash()]);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'policies' => $policies,
            'action' => 'Update',
            'user' => $this->getUser(),
            'article' => $article,
            'featureImageZone' => $zone->getTwigContext(),
            'isCommsOfficer' => (new ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser()),
        ])->setTitle("Update {$article->getTitle()}"));
    }


    /**
     * Update a news article
     *
     * @param Model $article
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Resource\Exception
     */
    public function postUpdate(Model $article)
    {
        try {

            /* TODO: Check that the current session user is allowed to modify this $article
             */

            $this->persist($article, $this->getRequest()->post);

            /* Save a draft of the news article
             * This can occur from either the Create or Update context
             * Update the form action accordingly
             */
            if ($this->getRequest()->isXhr()) {

                /* Current url should secretly update to the Update route
                 */
                $url = $this->getRouter()->generate('commsNews.getUpdate', ['articleId' => $article->getId()]);

                /* Form action should secretly update to the Update route
                 */
                $formAction = $this->getRouter()->generate('commsNews.postUpdate', ['articleId' => $article->getId()]);

                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true,
                    'url' => $url,
                    'formAction' => $formAction
                ]));
            } else {

                /* Otherwise, you're the creator and you're editing this thing normally
                 * Go to the detail screen to see the outcome of your changes
                 * and also use that screen to advance to the next step
                 */
                $nextStep = $this->getRouter()->generate('commsNews.getDetail', ['articleId' => $article->getId()]);
                $this->getResponse()->redirect->afterPost($nextStep);
                return;
            }

            return;
        } catch (MapperException|RouteNotFound|\PDOException|\UnexpectedValueException|\Schoolbox\Resource\Exception $e) {
            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => $e->getMessage()
                ]));

                return;
            }

            throw $e;
        }
    }


    /**
     * Persist model to db during news article authorship
     *
     * @param Model                          $article
     * @param array|\Aura\Web\Request\Values $data
     * @return void
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Resource\Exception
     */
    private function persist(Model $article, $data): void
    {
        $article
            ->setTitle($data['title'])
            ->setBody($data['body'])
            ->setBlurb($data['blurb'])
            ->setTopics($this->topicMapper->findByColumn('newsTopicID', $data['topics']));

        /* Once it's passed Moderation, only Moderators can alter contents.
         * Tough luck, wren; in accordance with the po-po
         */

        /* Exception thrown when an invalid date is submitted; use last known value instead
         * This can occur with bad input; or when datepicker is still processing strtotime shuriken
         */
        try {
            $article->setPublishAt(\Alaress_Date_Format::newLocal($data['publishAt']));
        } catch (InvalidArgumentException $e) {
            // noop
        }

        try {
            $article->setExpiresAt(\Alaress_Date_Format::newLocal($data['expiresAt']));
        } catch (InvalidArgumentException $e) {
            // noop
        }

        /* If any Feature Image was provided, remember it
         * Otherwise clear it (remove a Feature Image)
         */
        if (\is_array($data['attachment'])) {
            $article->setFeatureImage((new CollectorFactory())->build($data['attachment'] ?: [])->getFirst());
        } else {
            $article->setFeatureImage(null);
        }

        $this->newsMapper->save($article);
    }


    /**
     * Get a list of available news topics that can be assigned to an article
     */
    public function getTopics()
    {
        $this->getResponse()->content->set($this->buildJsonOutput($this->topicMapper->findAll()));
    }


    /**
     * Load a news article so you can read its contents (the detail screen)
     *
     * @param Model $article
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    public function getDetail(Model $article)
    {
        $policies = $this->policyMapper->findAll()->indexBy('identifier');

        $moderationRequired = $policies->getOffset('global_moderation')->getValue();

        /* Figure out if this was Saved For Later for the current user
         */
        $saveForLater    = $policies->getOffset('save_for_later')->getValue();
        $isSavedForLater = false;

        if ($saveForLater) {
            try {
                $this->saveForLaterMapper->findOneByColumnList([
                    'user_id' => $this->getUser()->getId(),
                    'article_id' => $article->getId()
                ]);

                $isSavedForLater = true;
            } catch (MapperException $e) {
                // noop; default is fine
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/detail.twig', [
            'policies' => $policies,
            'article' => $article,
            'moderationRequired' => $moderationRequired,
            'isSavedForLater' => $isSavedForLater,
            'isCommsOfficer' => (new ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser()),
            'isAuthor' => (new ValidUserIsAuthor())->isSatisfiedBy($this->getUser(), $article),
            'isEditable' => (new ValidUserCanEdit())->isSatisfiedBy($this->getUser(), $moderationRequired, $article),
            'isAcceptable' => (new Moderation\ValidUserCanAccept())->isSatisfiedBy($this->getUser(), $article),
            'isRejectable' => (new Moderation\ValidUserCanReject())->isSatisfiedBy($this->getUser(), $article),
            'isDeletable' => (new ValidUserCanDelete())->isSatisfiedBy($this->getUser(), $moderationRequired, $article)
        ])->setTitle($article->getTitle()));
    }


    /**
     * Delete an article
     *
     * @param Model $article
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function postDelete(Model $article)
    {
        try {
            $policies = $this->policyMapper->findAll()->indexBy('identifier');

            $moderationRequired = $policies->getOffset('global_moderation')->getValue();

            /* Try to delete this article
             *
             * Moderators can delete any article, but authors can only delete articles
             * that haven't been witnessed by the world. Such articles have to be unaccepted
             * and unpublished
             */
            if ((new ValidUserCanDelete())->isSatisfiedBy($this->getUser(), $moderationRequired, $article)) {
                $article->setDeletedAt(\Alaress_Date_Format::now());
                $this->newsMapper->save($article);

                /* If you're a moderator, go back to the Moderation list
                 *
                 * TODO: Otherwise go...somewhere else? I don't have a component or dashboard yet
                 */
                $nextStep = '/';

                if ((new ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser())) {
                    $nextStep = $this->getRouter()->generate('commsNewsModeration.getList');
                }

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

            /* Couldn't delete this article; maybe you weren't allowed
             */
            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => "You don't have permission to delete this article."
                ]));

                return;
            }

            throw $this->errorMapper->generateException(ErrorMapper::ERROR_COMMS_NEWS_CANNOT_DELETE);
        } catch (MapperException|RouteNotFound|\Alaress\Database\Predicate\InvalidColumnException|\PDOException|DefinitionException|\UnexpectedValueException|Exception $e) {
            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->status->set(400);
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => false,
                    'message' => $e->getMessage()
                ]));

                return;
            }

            throw $e;
        }
    }


    /**
     * Get a list of articles that were audienced to me
     * This is the central piece of the puzzle
     *
     * TODO: Incomplete
     *
     * @param int $topicId
     * @throws RouteNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \OutOfRangeException
     */
    public function getListByTopic(int $topicId)
    {
        if ($this->getRequest()->isXhr()) {

            /* Querystring params:
             * The page number to obtain
             * Any requested limits via component configs
             */
            $pageNum = $this->determinePageNum($this->getRequest()->query[Mapper::PAGE_NUMBER_PARAM]);
            $limit = $this->determineLimit($this->getRequest()->query[Mapper::LIMIT_PARAM]);

            $querystring = http_build_query([
                Mapper::PAGE_NUMBER_PARAM => $pageNum,
                Mapper::LIMIT_PARAM => $limit
            ]);

            $lists = new Lists($this->newsMapper, $this->policyMapper, $this->saveForLaterMapper);

            /* Figure out the slug from the id
             */
            $slug = $this->determineSlug($topicId);

            /* Get this an accompanying paginator
             */
            $url = $this->getRouter()->generate('commsNews.getIndex', ['slug' => $slug]) . "?{$querystring}";

            $paginator = $lists->generatePaginator($url, $topicId, $limit);

            $outcome = $lists->findForTopic($this->getUser(), $topicId, $limit, $pageNum);

            /* Merge a whole bunch of other settings too
             */
            $outcome = array_merge($outcome, [
                'compactMode' => $this->getRequest()->query['compactMode'],
                'paginator' => $paginator,
                'pageNum' => $pageNum,
                'limit' => $limit,
                'showPaginator' => $this->getRequest()->query[Mapper::PAGE_NUMBER_PARAM]
            ]);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'success' => true,
                'title' => $this->getDi()->get('l10n')->translate('news', false, true),
                'url' => $url,
                'fragment' => $this->buildUnthemedHtmlOutput('@commsNews/list.twig', $outcome)->getAsString()
            ]));
        }
    }


    /**
     * @param string $slug
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws RouteNotFound
     * @throws \OutOfRangeException
     */
    public function getIndex(string $slug)
    {
        /* News Topics were never obtainable by slug
         * We always fetched everything, then manually sorted by slug
         * Inherit this situation, improvise and adapt (or just use ids...)
         */
        $topics = $this->topicMapper->findAll();
        $topicId = $this->determineSelectedTopicId($topics, $slug);

        /* Querystring params:
         * The page number to obtain
         * Any requested limits TODO: This is a config option that users can't control on this screen
         */
        $pageNum = $this->determinePageNum($this->getRequest()->query[Mapper::PAGE_NUMBER_PARAM]);
        $limit = $this->determineLimit($this->getRequest()->query[Mapper::LIMIT_PARAM]) ?: self::ARTICLES_PER_PAGE;

        $querystring = http_build_query([
            Mapper::PAGE_NUMBER_PARAM => $pageNum,
            Mapper::LIMIT_PARAM => $limit
        ]);

        $lists = new Lists($this->newsMapper, $this->policyMapper, $this->saveForLaterMapper);

        /* Figure out the slug from the id
         */
        $slug = $this->determineSlug($topicId);

        /* Get this an accompanying paginator
         */
        $url = $this->getRouter()->generate('commsNews.getIndex', ['slug' => $slug]) . "?{$querystring}";

        $paginator = $lists->generatePaginator($url, $topicId, $limit);

        $outcome = $lists->findForTopic($this->getUser(), $topicId, $limit, $pageNum);

        /* Always provide paginator from this context
         */
        $outcome = array_merge($outcome, [
            'paginator' => $paginator,
            'showPaginator' => true
        ]);

        /* The initial data set to load, given the params provided
         */
        $initialContent = $this->buildUnthemedHtmlOutput('@commsNews/list.twig', $outcome)->getAsString();

        /* Merge a whole bunch of other settings too
         */
        $outcome = array_merge($outcome, [
            'initialContent' => $initialContent,
            'topics' => $topics,
            'topicAll' => Mapper::TOPIC_ALL_ID,
            'selectedTopicId' => $topicId,
            'pageNum' => $pageNum,
            'limit' => $limit
        ]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/index.twig', $outcome));
    }


    /**
     * Redirect to the actual index location, arranged by topic
     */
    public function getIndexRedirect()
    {
        $this->getResponse()->redirect->to($this->getRouter()->generate('commsNews.getIndex', ['slug' => 'all']));
    }

    /**
     * Determine a slug from a topic id
     *
     * @param int $topicId
     * @return string
     */
    private function determineSlug(int $topicId): string
    {
        try {
            return TopicMapper::getInstance()->findById($topicId)->getSlug();
        } catch (MapperException $e) {
            // unknown topic id; just return the default All Topics slug
        }

        return Mapper::TOPIC_ALL_SLUG;
    }

    /**
     * The page number to obtain; default to 1
     * Otherwise use the page number provided
     *
     * @param null|string $param
     * @return int
     */
    private function determinePageNum(?string $param): int
    {
        return (int)$param ?: 1;
    }


    /**
     * From a slug, figure out what topic matches it
     *
     * By default, select the All tab...
     * but try to find the topic whose slug matches the url param
     *
     * @param Collection|Topic[] $topics
     * @param string             $slug
     * @return int
     */
    private function determineSelectedTopicId(Collection $topics, string $slug): int
    {
        $selectedTopicId = Mapper::TOPIC_ALL_ID;

        foreach ($topics as $topic) {
            if ($topic->getSlug() === $slug) {
                return $topic->getId();
            }
        }

        return $selectedTopicId;
    }


    /**
     * $limit can be 'all' or unspecified; this equates to "don't impose a limit"
     * ("all" could come from a component config option)
     *
     * @param null|string $limit
     * @return int
     */
    private function determineLimit(?string $limit): int
    {
        /* limit not set at all; don't impose limits
         */
        if (('all' === $limit) || (null === $limit) || !(int)$limit) {
            return 0;
        }

        /* use whatever was provided
         */
        return (int)$limit;
    }
}
