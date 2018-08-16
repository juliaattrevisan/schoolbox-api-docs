<?php
/**
 * Controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Comms\News;

use Alaress\Database\Connector\IConnector;
use Alaress\Json\Decorator\PropertyDecorator;
use Alaress\Web\Controller\Base;
use Alaress\Web\Output\ThemedHtml;
use Alaress\Web\Output\Html;
use Aura\Router\Exception\RouteNotFound;
use External_Class;
use External_User;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Audience\BusinessRules;
use Schoolbox\Comms\News\Audience\RelationshipFilterer;
use Schoolbox\Comms\News\Policy;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Comms\News\Statistics\ImpressionsConversionsOpens;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\Topic;
use Schoolbox\News\TopicMapper;
use Schoolbox\Permission\Access;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

class Controller extends Base
{


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
     * @var Statistics\Mapper
     */
    private $statisticsMapper;

    /**
     * @var Statistics\Cumulative\Mapper
     */
    private $statisticsCumulativeMapper;

    /**
     * @var IConnector
     */
    private $databaseInternal;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var Access\UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var External_User
     */
    private $externalUser;

    /**
     * @var External_Class
     */
    private $externalClass;

    /**
     * @var Audience\Mapper
     */
    private $audienceMapper;

    /**
     * @var RoleAccessMapper
     */
    private $roleAccessMapper;


    /**
     * Controller constructor.
     *
     * @param ErrorMapper             $errorMapper
     * @param Mapper                  $newsMapper
     * @param Policy\Mapper           $policyMapper
     * @param TopicMapper             $topicMapper
     * @param SaveForLater\Mapper     $saveForLaterMapper
     * @param Statistics\Mapper       $statisticsMapper
     * @param Statistics\Cumulative\Mapper $statisticsCumulativeMapper
     * @param IConnector              $databaseInternal
     * @param Folder\Mapper           $folderMapper
     * @param Access\UserAccessMapper $userAccessMapper
     * @param UserMapper              $userMapper
     * @param External_User           $externalUser
     * @param External_Class          $externalClass
     * @param Audience\Mapper         $audienceMapper
     * @param RoleAccessMapper        $roleAccessMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        Mapper $newsMapper,
        Policy\Mapper $policyMapper,
        TopicMapper $topicMapper,
        SaveForLater\Mapper $saveForLaterMapper,
        Statistics\Mapper $statisticsMapper,
        Statistics\Cumulative\Mapper $statisticsCumulativeMapper,
        IConnector $databaseInternal,
        Folder\Mapper $folderMapper,
        Access\UserAccessMapper $userAccessMapper,
        UserMapper $userMapper,
        External_User $externalUser,
        External_Class $externalClass,
        Audience\Mapper $audienceMapper,
        RoleAccessMapper $roleAccessMapper
    ) {
        $this->errorMapper = $errorMapper;
        $this->newsMapper = $newsMapper;
        $this->policyMapper = $policyMapper;
        $this->topicMapper = $topicMapper;
        $this->saveForLaterMapper = $saveForLaterMapper;
        $this->statisticsMapper = $statisticsMapper;
        $this->statisticsCumulativeMapper = $statisticsCumulativeMapper;
        $this->audienceMapper = $audienceMapper;
        $this->roleAccessMapper = $roleAccessMapper;

        /* Hyperdrive injection
         */
        $this->databaseInternal = $databaseInternal;
        $this->folderMapper = $folderMapper;
        $this->userAccessMapper = $userAccessMapper;
        $this->userMapper = $userMapper;
        $this->externalUser = $externalUser;
        $this->externalClass = $externalClass;
    }


    /**
     * Provide form to create a news article
     *
     * @api GET /comms/news/create
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
     * @api POST /comms/news/create
     *
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     * @throws RouteNotFound
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
     * @api GET /comms/news/{articleId}/update
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

        /* Audiencing decoration
         */

        /** @var Html $outputFormatter */
        $outputFormatter = $this->getDi()->get('webOutput:nochrome');

        $audiencingData = (new Audience\Segment\Transactor($this->getUser(), $this->audienceMapper, $this->folderMapper, $this->roleAccessMapper, $moderationRequired))->load($article);
        $audiencingHtml = (new Audience\Segment\HtmlFormatter($outputFormatter))->decorate($audiencingData);

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'policies' => $policies,
            'action' => 'Update',
            'user' => $this->getUser(),
            'article' => $article,
            'featureImageZone' => $zone->getTwigContext(),
            'audience' => $audiencingHtml,
            'isCommsOfficer' => (new ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser()),
            'relationshipParentsOf' => Audience\Segment\Codec::AUDIENCE_SEPARATOR . Audience\Model::RELATIONSHIP_PARENTS_OF,
            'relationshipTeachersOf' => Audience\Segment\Codec::AUDIENCE_SEPARATOR . Audience\Model::RELATIONSHIP_TEACHERS_OF
        ])->setTitle("Update {$article->getTitle()}"));
    }


    /**
     * Update a news article
     *
     * @api POST /comms/news/{articleId}
     *
     * @param Model $article
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     * @throws RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
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
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
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

        /* Set audiencing options via the hyperdrive
         */
        $transactor = new Audience\Segment\Transactor($this->getUser(), $this->audienceMapper, $this->folderMapper, $this->roleAccessMapper, $this->isGlobalModerationEnabled());
        $transactor->save($article, $this->getRequest()->post['audience']);
    }


    /**
     * Get a list of available news topics that can be assigned to an article
     *
     * @api GET /comms/news/topics
     */
    public function getTopics()
    {
        $this->getResponse()->content->set($this->buildJsonOutput($this->topicMapper->findAll()));
    }


    /**
     * Load a news article so you can read its contents (the detail screen)
     *
     * @api GET /comms/news/{articleId}
     *
     * @param Model $article
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws InvalidArgumentException
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

        /* Impression referrer
         */
        $referrer = $this->getRequest()->query[Querystring::REFERRER];

        /* If you don't have an http referrer, disregard the $referrer specified in the url.
         *
         * This could occur if you copy/paste a link to someone in email or instant messenger,
         * and they visit it from that place. Consider them a Non-referred open, rather than
         * a Conversion.
         */
        if (null === $this->getRequest()->client->getReferer()) {
            $referrer = null;
        }

        /* Record this Conversion/Open
         * referrer value determines what kind it was
         */
        (new ImpressionsConversionsOpens($this->databaseInternal, $this->statisticsMapper, $this->statisticsCumulativeMapper))
            ->saveConversionOpen(
                $article,
                $this->getUser()->getId(),
                $this->getUser()->getRole()->getType(),
                $this->getUser()->getRole()->getId(),
                $referrer
            );

        // don't supply the extra twig-necessary data if just returning JSON
        if ($this->isJsonRequest()) {
            // mutate the default map slightly
            $map = $article->getDefaultJsonMap();
            $map['featureImage'] = [ 'size', 'mime' ];

            $data = [
                'article' => $article->jsonSerialize(
                    new PropertyDecorator($map, new LinkDecorator($this->router, $this->authorizer, $this->getUser()))
                ),
                'isSavedForLater' => $isSavedForLater,
            ];

            // wish this were easier to do...
            unset($data['article']['featureImage']['_links']['fetch']);

        } else {
            $data = [
                'policies' => $policies,
                'article' => $article,
                'moderationRequired' => $moderationRequired,
                'isSavedForLater' => $isSavedForLater,
                'isCommsOfficer' => (new ValidUserIsCommsOfficer())->isSatisfiedBy($this->getUser()),
                'isAuthor' => (new ValidUserIsAuthor())->isSatisfiedBy($this->getUser(), $article),
                'isEditable' => (new ValidUserCanEdit())->isSatisfiedBy($this->getUser(), $moderationRequired, $article),
                'isAcceptable' => (new Moderation\ValidUserCanAccept())->isSatisfiedBy($this->getUser(), $article),
                'isRejectable' => (new Moderation\ValidUserCanReject())->isSatisfiedBy($this->getUser(), $article),
                'isUnpublishable' => (new Moderation\ValidUserCanUnpublish())->isSatisfiedBy($this->getUser(), $article),
                'isDeletable' => (new ValidUserCanDelete())->isSatisfiedBy($this->getUser(), $moderationRequired, $article)
            ];
        }

        $output = $this->buildContent('@commsNews/detail.twig', $data);

        if ($output instanceof ThemedHtml) {
            $output->setTitle($article->getTitle())
                   ->setTab('news');
        }

        $this->response->content->set($output);
    }


    /**
     * Delete an article
     *
     * @api POST /comms/news/{articleId}/delete
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
                $this->newsMapper->delete($article);

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
     * @api GET /comms/news/list
     *
     * @param int|null $year
     * @param int|null $month
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws \PDOException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getListByTopic(?int $year, ?int $month)
    {
        $params = new Querystring($this->getRequest()->query);
        $topics = $this->topicMapper->findAll();

        /* The requested data set to load, given the params provided
         */
        if ($year) {
            $urlPrefix = $this->getRouter()->generate('commsNews.getArchive', [ 'year' => $year, 'month' => $month ]);
        } else {
            $urlPrefix = $this->getRouter()->generate('commsNews.getIndex');
        }

        $partialData = $this->generatePartial($urlPrefix, $params, $topics, $params->getHidePaginator(), $year, $month);

        if (!$this->isXhrRequest()) {
            // if it's NOT XHR, go for API version
            $this->response->content->set($this->buildJsonOutput([
                'articles' => $partialData['articles']
            ]));

            return;
        }

        $content     = $this->buildUnthemedHtmlOutput('@commsNews/partial.twig', $partialData)->getAsString();
        $viewAllLink = "{$this->getRouteIfAuthorized('commsNews.getIndex')}?topic={$params->getSlug()}";

        $this->getResponse()->content->set($this->buildJsonOutput([
            'success' => true,
            'title' => $this->getDi()->get('l10n')->translate('news', false, true),
            'url' => $partialData['currentUrl'],
            'fragment' => $content,
            'slug' => $params->getSlug(),
            'viewAllLink' => $partialData['articles']->count() ? $viewAllLink : null
        ]));
    }


    /**
     * News Archive
     *
     * @api GET /comms/news/archive{/year,month}
     *
     * @param int|null $year
     * @param int|null $month
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws \PDOException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getArchive(int $year = null, int $month = null)
    {
        $params = new Querystring($this->getRequest()->query);
        $params->setReferrer(Statistics\Mapper::REFERRER_ARCHIVE);

        $topics = $this->topicMapper->findAll();

        /* The initial data set to load, given the params provided
         * Compact Mode is hardcoded to be disabled from this context
         * HidePaginator is always false in this context
         */
        $urlPrefix = $this->getRouter()->generate('commsNews.getArchive', ['year' => $year, 'month' => $month]);

        $partialData = $this->generatePartial($urlPrefix, $params, $topics, false, $year, $month);
        $content = $this->buildUnthemedHtmlOutput('@commsNews/partial.twig', $partialData)->getAsString();

        $availableYearsAndMonths = $this->newsMapper->findYearsAndMonthsWithArticles($partialData['articleIdsForUser']);

        // Default to filtering by the most recent year if none is provided
        if (null === $year) {
            $year = array_keys($availableYearsAndMonths)[0];
        }

        /* Merge a whole bunch of other settings too
         * Specifically applies to the index page which loads slightly differently than component variant
         */
        $data = [
            'initialContent' => $content,
            'topics' => $topics,
            'topicAll' => Mapper::TOPIC_ALL_SLUG,
            'slug' => $params->getSlug(),
            'referrer' => $params->getReferrer(),

            /* Archive-specific vars that don't appear anywhere else
             */
            'availableYearsAndMonths' => $availableYearsAndMonths,
            'year' => $year,
            'month' => $month,
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/archive.twig', $data));
    }


    /**
     * Get a list of articles audienced to you
     *
     * @api GET /comms/news
     *
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws \PDOException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getIndex()
    {
        $params = new Querystring($this->getRequest()->query);
        $params->setReferrer(Statistics\Mapper::REFERRER_INDEX);
        $topics = $this->topicMapper->findAll();

        /* The initial data set to load, given the params provided
         * Compact Mode is hardcoded to be disabled from this context
         * HidePaginator is always false in this context
         */
        $urlPrefix = $this->getRouter()->generate('commsNews.getIndex');

        $partialData = $this->generatePartial($urlPrefix, $params, $topics, false, null, null);
        $content = $this->buildUnthemedHtmlOutput('@commsNews/partial.twig', $partialData)->getAsString();

        /* Merge a whole bunch of other settings too
         * Specifically applies to the index page which loads slightly differently than component variant
         */
        $data = [
            'initialContent' => $content,
            'topics' => $topics,
            'topicAll' => Mapper::TOPIC_ALL_SLUG,
            'slug' => $params->getSlug(),
            'referrer' => $params->getReferrer(),

            /* For configuring the rss button which changes per topic chosen
             */
            'key' => $this->getUser()->getKey(),
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/index.twig', $data));
    }


    /**
     * Index of articles you've authored
     *
     * @api GET /comms/news/authored
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getAuthored()
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/authored.twig', []));
    }


    /**
     * Get a list of articles that the logged in user authored
     *
     * @api GET /comms/news/me/articles
     */
    public function getAuthoredArticles()
    {
        $args = [
            'order' => [
                'created_at' => 'desc'
            ]
        ];

        $articles = $this->newsMapper->findByColumn('author_id', $_SESSION['user']->getId(), $args);

        $this->getResponse()->content->set($this->buildJsonOutput($articles));
    }


    /**
     * Generate a partial fragment containing articles
     * Used in both synchronous and asynchronous requests
     *
     * @param string             $urlPrefix
     * @param Querystring        $params
     * @param Collection|Topic[] $topics
     * @param bool               $hidePaginator
     * @param int|null           $year
     * @param int|null           $month
     * @return array
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws \PDOException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    private function generatePartial(string $urlPrefix, Querystring $params, Collection $topics, bool $hidePaginator, ?int $year, ?int $month): array
    {
        $topicData = (new Lists(
            $this->getConfigLoader()->get('search_type_limit'),
            $this->newsMapper,
            $this->policyMapper,
            $this->saveForLaterMapper,
            $this->statisticsMapper,
            $this->statisticsCumulativeMapper,
            $this->databaseInternal,
            $this->folderMapper,
            $this->userAccessMapper,
            $this->userMapper,
            $this->externalUser,
            $this->externalClass
        ))->findForTopic(
            $urlPrefix,
            $params,
            $this->getUser(),
            $topics,
            $year,
            $month
        );

        /* Always provide paginator from this context
         */
        $partialData = array_merge($topicData, [
            'hidePaginator' => $hidePaginator,
            'compactMode' => $params->getCompactMode(),
            'pageNum' => $params->getPageNum(),
            'referrer' => $params->getReferrer()
        ]);

        return $partialData;
    }


    /**
     * Compute the recipients so author can identify
     *
     * @api /comms/news/:articleId/recipients
     *
     * @param Model $article
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getRecipients(Model $article)
    {
        $userIds = (new Audience\UserResolver(
            new BusinessRules(),
            $this->databaseInternal,
            $this->externalClass,
            new RelationshipFilterer(
                $this->databaseInternal,
                $this->userMapper,
                $this->externalUser,
                $this->folderMapper,
                $this->externalClass,
                $this->userAccessMapper
            ),
            $this->audienceMapper
        ))->findUsersForArticle($article->getId());

        if (\count($userIds)) {
            $users = $this->userMapper->findByColumn('id', $userIds)->sortBy('lastname', 'asc');
        } else {
            $users = $this->userMapper->createCollection();
        }

        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@commsNews/recipients.twig', ['users' => $users]));
    }


    /**
     * Returns whether global moderation is enabled
     *
     * @return bool
     */
    private function isGlobalModerationEnabled(): bool
    {
        /** @var Policy\Model $policy */
        $policy = $this->policyMapper->findByColumn('identifier', 'global_moderation')->first();
        return (bool)$policy->getValue();
    }
}
