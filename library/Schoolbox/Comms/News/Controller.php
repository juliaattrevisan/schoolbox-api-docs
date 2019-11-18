<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Database\Connector\IConnector;
use Alaress\Json\Decorator\PropertyDecorator;
use Alaress\Specification\Exception\UnsatisfiedException;
use Alaress\Web\Controller\Base;
use Alaress\Web\Output\ThemedHtml;
use Aura\Router\Exception\RouteNotFound;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Discussion\ThreadLocator;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Comms\News\Statistics\Interactions;
use Schoolbox\Comms\News\Validation\Factory;
use Schoolbox\Comms\News\Moderation\Controller as ModerationController;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Discussion\Thread;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Comms\News\Topic;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Storage;
use UnexpectedValueException;
use Schoolbox\User;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;

class Controller extends Base
{

    /**
     * @var Mapper
     */
    private $newsMapper;

    /**
     * @var Topic\Mapper
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
     * @var Mapper
     */
    private $folderMapper;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var Audience\Mapper
     */
    private $audienceMapper;

    /**
     * @var Audience\ArticleResolver
     */
    private $articleResolver;

    /**
     * @var Audience\UserResolver
     */
    private $userResolver;

    /** @var Audience\Segment\StrategyFactory */
    private $strategyFactory;

    /** @var Access */
    private $access;

    /** @var Thread\Mapper */
    private $threadMapper;

    /** @var ErrorMapper */
    private $errorMapper;

    /** @var CommentMapper */
    private $commentMapper;

    /**
     * Default number of items to display in case config is broken
     */
    private const DEFAULT_SEARCH_LIMIT = 10;


    /**
     * @param Mapper                           $newsMapper
     * @param Topic\Mapper                     $topicMapper
     * @param SaveForLater\Mapper              $saveForLaterMapper
     * @param Statistics\Mapper                $statisticsMapper
     * @param Statistics\Cumulative\Mapper     $statisticsCumulativeMapper
     * @param Audience\Mapper                  $audienceMapper
     * @param Folder\Mapper                    $folderMapper
     * @param IConnector                       $databaseInternal
     * @param Audience\ArticleResolver         $articleResolver
     * @param Audience\UserResolver            $userResolver
     * @param Audience\Segment\StrategyFactory $strategyFactory
     * @param Access                           $access
     * @param Thread\Mapper                    $threadMapper
     * @param ErrorMapper                      $errorMapper
     * @param CommentMapper                    $commentMapper
     */
    public function __construct(
        Mapper $newsMapper,
        Topic\Mapper $topicMapper,
        SaveForLater\Mapper $saveForLaterMapper,
        Statistics\Mapper $statisticsMapper,
        Statistics\Cumulative\Mapper $statisticsCumulativeMapper,
        Audience\Mapper $audienceMapper,
        Folder\Mapper $folderMapper,
        IConnector $databaseInternal,
        Audience\ArticleResolver $articleResolver,
        Audience\UserResolver $userResolver,
        Audience\Segment\StrategyFactory $strategyFactory,
        Access $access,
        Thread\Mapper $threadMapper,
        ErrorMapper $errorMapper,
        CommentMapper $commentMapper
    ) {
        $this->newsMapper                 = $newsMapper;
        $this->topicMapper                = $topicMapper;
        $this->saveForLaterMapper         = $saveForLaterMapper;
        $this->statisticsMapper           = $statisticsMapper;
        $this->statisticsCumulativeMapper = $statisticsCumulativeMapper;
        $this->audienceMapper             = $audienceMapper;
        $this->folderMapper               = $folderMapper;
        $this->databaseInternal           = $databaseInternal;
        $this->articleResolver            = $articleResolver;
        $this->userResolver               = $userResolver;
        $this->strategyFactory            = $strategyFactory;
        $this->access                     = $access;
        $this->threadMapper               = $threadMapper;
        $this->errorMapper                = $errorMapper;
        $this->commentMapper              = $commentMapper;
    }

    /* CRUD actions */

    /**
     * Provide form to create a news article
     *
     * @api   GET /news/create
     * @since 18.1
     *
     * @throws MapperException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws UnknownEntityTypeException
     * @throws RouteNotFound
     */
    public function getCreate(): void
    {
        /* Spawn an empty news with default settings
         */
        $article = $this->newsMapper->createModel()
                                    ->setPublishAt(\Alaress_Date_Format::now())
                                    ->setExpiresAt(\Alaress_Date_Format::now());

        /* Policies: Publish Date Offset
         */
        $publishDateOffset = (string)$this->getConfigLoader()->get(ConfigMapper::_PUBLISH_DATE_OFFSET);

        if ((new ValidDateOffset())->isSatisfiedBy($publishDateOffset)) {
            $article->getPublishAt()->modify($publishDateOffset)->endOfDay();
        }

        /* Policies: Expiry Date Offset
         */
        $expireDateOffset = (string)$this->getConfigLoader()->get(ConfigMapper::_EXPIRY_DATE_OFFSET);

        if ((new ValidDateOffset())->isSatisfiedBy($expireDateOffset)) {
            $article->getExpiresAt()->modify($expireDateOffset)->endOfDay();
        }

        /* Feature image
         */
        $zone = (new Zone('featureImage'))
            ->setMulti(false)
            ->setAttachPrompt('Attach a feature image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*');

        // Prefill audience
        $audienceSegments = [];
        $params = new Querystring($this->getRequest()->query);
        $folderId = $params->getFolderId();
        $entityType = $params->getEntityType();

        // Hard-coded to folder (for now)
        if ($folderId && $entityType && $entityType === Audience\Segment\Strategy\Folder::getEntityType()) {

            /** @var Audience\Segment\Strategy\Folder $folderStrategy */
            $folderStrategy = $this->strategyFactory->makeSimpleStrategy($entityType);

            // Ensure the user is permitted to target this entity type and has the permissions to audience into the folder
            if ($folderStrategy->canAudienceToEntityType($this->getUser()) &&
                (Access::getInstance()->getFolderAccess($this->getUser(),$folderId) >= UserAccess::ACCESS_WRITE ||
                    $folderStrategy->canAudienceToAnyClassOrGroup($this->getUser()))
            ) {
                $codec = new Audience\Segment\Codec();
                $label = $this->folderMapper->findCheaplyById($folderId)->getNameWithCodes();

                $audienceSegment = new Audience\SegmentSelectizeOption(
                    $codec->encodeSimpleAttributes($entityType, $folderId, Audience\Model::RELATIONSHIP_SELF),
                    $label
                );

                $audienceSegment->addLabel($entityType, $codec->encodeValue($folderId), $label);
                $audienceSegments[] = $audienceSegment;
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'action'               => 'Create',
            'article'              => $article,
            'featureImageZone'     => $zone->getTwigContext(),
            'isAutoSavable'        => true,
            'encodedSegments'      => $audienceSegments,
            'availableEntityTypes' => $this->getAvailableAudienceTypeStrategies(),
            'returnUrl'            => $this->getExplicitReturnUrl(),
            'entityTypesData'      => $this->getEntityTypesData(),
        ])->setTab('news')->setTitle("Create {$this->getDi()->get('sitePage')->getLocalisedTerm('news', false, true)}"));
    }


    /**
     * Redirects to `getCreate`, with a predefined audience brick for the given
     * folder.
     *
     * Implemented so that it can be permissioned uniquely to creating news.
     *
     * @api   GET /news/create/folder/{folderId:\d+}
     * @since 18.1
     *
     * @param int $folderId
     * @throws RouteNotFound
     */
    public function getCreateInFolder(int $folderId): void
    {
        $params = new Querystring($this->getRequest()->query);
        $params->setFolderId($folderId);
        $params->setEntityType(Audience\Segment\Strategy\Folder::getEntityType());
        $params->setReturnUrl(Querystring::encode('/homepage/' . $folderId));

        $this->response->redirect->to(
            $this->router->generate('commsNews.getCreate') . '?' . $params
        );
    }


    /**
     * Create a news article
     *
     * @api   POST /news/create
     * @since 18.1
     *
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     * @throws RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     * @throws \DomainException
     */
    public function postCreate(): void
    {
        /* Set YOU as the initial author
         * Otherwise, when a moderator edits your article, they might assume direct authorship instead
         */
        $article = $this->newsMapper->createModel()
                                    ->setAuthor($this->getUser())
                                    ->setAudience(new GenericCollection(Audience\Model::class));
        $this->postUpdate($article);
    }


    /**
     * Provide form to update a news article
     *
     * @api   GET /news/{articleId}/update
     * @since 18.1
     *
     * @param Model $article
     * @throws InvalidArgumentException
     * @throws MapperException\InsufficientHydrationException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \UnexpectedValueException
     * @throws RouteNotFound
     */
    public function getUpdate(Model $article): void
    {
        /* Feature image
         */
        $zone = (new Zone('featureImage'))
            ->setMulti(false)
            ->setAttachPrompt('Attach a feature image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*');

        if ($article->getFeatureImage()) {
            $zone->attachFiles([$article->getFeatureImage()]);
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'action'                 => 'Update',
            'article'                => $article,
            'featureImageZone'       => $zone->getTwigContext(),
            'encodedSegments'        => $this->getAudienceSegments($article),
            'isAutoSavable'          => !$article->isSubmitted() || $article->isRejected(),
            'returnUrl'              => $this->getExplicitReturnUrl(),
            'availableEntityTypes'   => $this->getAvailableAudienceTypeStrategies(),
            'entityTypesData'        => $this->getEntityTypesData(),
        ])->setTab('news')->setTitle("Update {$article->getTitle()}"));
    }


    /**
     * Update a news article
     *
     * Must not attempt to submit/un-submit/approve/reject an article.
     *
     * It may change the approved subState, eg AwaitingPublication/Published/Expired
     *
     * @api   POST /news/{articleId}
     * @since 18.1
     *
     * @param Model $article
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     * @throws RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     * @throws \DomainException
     * @throws UnknownEntityTypeException
     * @throws InvalidArgumentException
     */
    public function postUpdate(Model $article): void
    {
        $data = $this->getRequest()->post->getArrayCopy();

        // Update article with proposed attributes
        $this->updateArticleAttributes($article, $data);

        // Update article with proposed audience. Cache existing audience for later efficient persistence
        $existingAudience = $article->getAudience()->indexBy($this->audienceMapper->getIdentifierProperty())->shallowClone();
        if (!$article->isApproved()) {
            $this->updateArticleAudience($article, $data['audience']);
        }
        // Validate proposed article + audience
        // (This protects against DOM manipulation + logic bugs, it should not be shown on normal work-flows)
        try{
            $this->validateArticleAndAudience($article);
        } catch (UnsatisfiedException $e){
            $this->getResponse()->status->set(400);
            $this->getResponse()->content->set($this->buildJsonOutput([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
            return;
        }

        // Persist article
        $this->newsMapper->save($article);

        // Restore existing audience onto article, and then update with posted audience
        $postedAudience = $article->getAudience()->shallowClone();
        $article->setAudience($existingAudience);

        // Perform intelligent update and persistence in specialised helper class
        $audienceTransactor = new Audience\Segment\Transactor($this->audienceMapper, $this->strategyFactory);
        $audienceTransactor->save($article, $postedAudience);

        $routeParams  = [
            'articleId' => $article->getId(),
            'article'   => $article,
        ];


        /* Save a draft of the news article
         * This can occur from either the Create or Update context
         * Update the form action accordingly
         */
        if ($this->getRequest()->isXhr()) {

            // Send the update page route, JS will update the window state to this
            $url = $this->getRouter()->generate('commsNews.getUpdate', $routeParams);
            if (isset($data[Querystring::RETURN_URL])) {
                $url .= '?' . http_build_query([Querystring::RETURN_URL => $data[Querystring::RETURN_URL]]);
            }

            // Send all the possible routes, JS will determine which ones to show where
            $updateRoute = $this->getRouter()->generate('commsNews.postUpdate', $routeParams);
            $submitRoute = $this->getRouteIfAuthorized('commsNewsModeration.postSubmit', $routeParams);
            $approveRoute = false;
            if ($article->canBeApproved()) {
                $approveRoute = $this->getRouteIfAuthorized('commsNewsModeration.postApprove', $routeParams);
            }

            $this->getResponse()->content->set($this->buildJsonOutput([
                'success'      => true,
                'articleId'    => $article->getId(),
                'url'          => $url,
                'returnUrl'    => $data[Querystring::RETURN_URL],
                'updateRoute'  => $updateRoute,
                'submitRoute'  => $submitRoute,
                'approveRoute' => $approveRoute
            ]));
        } else {

            /* Otherwise, you're the creator and you're editing this thing normally
             * Go to the detail screen to see the outcome of your changes
             * and also use that screen to advance to the next step
             */
            if ($article->isPublished() && $data[Querystring::RETURN_URL]) {
                $nextStep = Querystring::decode($data[Querystring::RETURN_URL]);
            } else {
                $nextStep = $this->getRouter()->generate('commsNews.getDetail', $routeParams);
                if (isset($data[Querystring::RETURN_URL])) {
                    $nextStep .= '?' . http_build_query([Querystring::RETURN_URL => $data[Querystring::RETURN_URL]]);
                }
            }
            $this->getResponse()->redirect->afterPost($nextStep);
            return;
        }
    }


    /**
     * Delete an article
     *
     * @api POST /news/{articleId}/delete
     * @since 18.1
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
    public function postDelete(Model $article): void
    {
        try {
            /* Try to delete this article
             *
             * Moderators can delete any article, but authors can only delete articles
             * that haven't been witnessed by the world. Such articles have to be unaccepted
             * and unpublished
             */

            $this->newsMapper->delete($article);

            $context = new ThreadLocator($this->threadMapper, $this->newsMapper);
            if ($article->getDiscussionThread()) {
                $context->deleteThread($article->getDiscussionThread(), $article);
            }

            /* Redirect to returnUrl if provided, otherwise to news feed.
             */

            if ($this->getRequest()->query->get(Querystring::RETURN_URL)) {
                $nextStep = Querystring::decode($this->getRequest()->query->get(Querystring::RETURN_URL));
            } else {
                $nextStep = $this->getRouter()->generate('commsNews.getIndex');
            }

            if ($this->getRequest()->isXhr()) {
                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true,
                    'redirect' => $nextStep
                ]));
            } else {
                $this->getResponse()->redirect->to($nextStep);
            }

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
     * Get a list of available news topics that can be assigned to an article
     *
     * @api GET /news/topics
     * @since 18.1
     */
    public function getTopics(): void
    {
        $this->getResponse()->content->set($this->buildJsonOutput($this->topicMapper->findAll()));
    }


    /**
     * Load a news article so you can read its contents (the detail screen)
     *
     * @api   GET /news/{articleId}
     * @since 18.1
     *
     * @param Model $article
     * @throws MapperException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws RouteNotFound
     * @throws UnknownEntityTypeException
     * @throws MapperException\InsufficientHydrationException
     */
    public function getDetail(Model $article): void
    {
        /* Figure out if this was Saved For Later for the current user
         */
        $saveForLater    = $this->getConfigLoader()->get(ConfigMapper::_SAVE_FOR_LATER);
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
         *
         * Even when you copy/paste a link to someone in email or instant messenger,
         * and they visit it from that place, "we" decided to  consider them as a conversion.
         */
        $referrer = $this->getRequest()->query[Querystring::REFERRER];

        /* Record this Conversion/Open
         * referrer value determines what kind it was
         */
        (new Interactions($this->databaseInternal, $this->statisticsMapper, $this->statisticsCumulativeMapper))
            ->saveOpen(
                $article,
                \Alaress_Date_Format::now(),
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
            $segment = $this->getSession()->getSegment('Schoolbox\Comms\News\Controller');
            $data = [
                'article'               => $article,
                'flashMessage'          => $segment->getFlash(ModerationController::ARTICLE_FLASH_MESSAGE_KEY),
                'isSavedForLater'       => $isSavedForLater,
                'returnUrl'             => $this->getExplicitReturnUrl(),
                'encodedSegments'       => $this->getAudienceSegments($article, true),
                'availableEntityTypes'  => $this->getAvailableAudienceTypeStrategies(true),
                'entityTypesData'       => $this->getEntityTypesData(false),
                'isPublishable'         => $this->generateValidSubmittedArticleSpec()->isSatisfiedBy($article),
                'policies'  => [
                    'moderationEnabled'     => $this->getConfigLoader()->get(ConfigMapper::_GLOBAL_MODERATION),
                    'commentsIsEnabled'     => $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED),
                ],
            ];
        }

        $output = $this->buildContent('@commsNews/detail.twig', $data);

        if ($output instanceof ThemedHtml) {
            $output->setTitle($article->getTitle())
                   ->setTab('news');
        }

        $this->response->content->set($output);
    }


    /* Homepage */

    /**
     * Get a list of articles that are audienced to me for homepage component
     *
     * @api GET /news/lists/homepage
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \PDOException
     * @throws InvalidArgumentException
     * @throws \UnderflowException
     */
    public function getListForHomepage() :void
    {
        $params = new Querystring($this->getRequest()->query);

        $partialData = $this->generatePartialForHomepage($params);

        if (!$this->isXhrRequest()) {
            // if it's NOT XHR, go for API version
            $this->response->content->set($this->buildJsonOutput([
                'articles' => $partialData['articles']
            ]));

            return;
        }

        $content = $this->buildUnthemedHtmlOutput('@commsNews/partial.twig', $partialData)->getAsString();

        $this->getResponse()->content->set($this->buildJsonOutput([
            'success' => true,
            'fragment' => $content
        ]));
    }


    /**
     * Generate a partial fragment containing articles for homepage
     *
     * @param Querystring $params
     * @return array
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \PDOException
     * @throws InvalidArgumentException
     * @throws \UnderflowException
     */
    private function generatePartialForHomepage(Querystring $params): array
    {
        $showArchiveNewsLink = false;
        $configuredSearchLimit = $this->getConfigLoader()->get('search_type_limit');
        $searchLimit           = null === $configuredSearchLimit ? self::DEFAULT_SEARCH_LIMIT : (int)$configuredSearchLimit;

        $topicData = (new Lists\Homepage(
            $searchLimit,
            $this->topicMapper,
            $this->newsMapper,
            $this->getConfigLoader()->get(ConfigMapper::_READ_MORE),
            $this->getConfigLoader()->get(ConfigMapper::_SAVE_FOR_LATER),
            $this->saveForLaterMapper,
            $this->statisticsMapper,
            $this->statisticsCumulativeMapper,
            $this->databaseInternal,
            $this->articleResolver,
            $this->commentMapper,
            $this->audienceMapper,
            $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED)
        ))->find(
            $params,
            $this->getUser()
        );

        if (\count($topicData['articles']) < 1) {
            $showArchiveNewsLink = $this->newsMapper->checkArchiveForHomepage((int) $params->getFolderId());
        }

        return array_merge($topicData, [
            'hidePaginator' => true,
            'compactMode' => $params->getCompactMode(),
            'referrer' => $params->getReferrer(),
            'folderId' => $params->getFolderId(),
            'showArchiveNewsLink' => $showArchiveNewsLink,
            'componentId' => $params->getComponentId(),
        ]);
    }


    /* Index */

    /**
     * Get a list of articles audienced to you
     *
     * @api GET /news
     * @since 18.1
     *
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function getIndex(): void
    {
        /* These referrers should be used, even when they're not explicitly provided in the url
         */
        $params = new Querystring($this->getRequest()->query);
        $params->setReferrer(Statistics\Mapper::REFERRER_INDEX);

        /* Merge a whole bunch of other settings too
         * Specifically applies to the index page which loads slightly differently than component variant
         */
        $data = [
            'topicAll' => Mapper::TOPIC_ALL_SLUG,
            'topic' => $params->getSlug() ?: 'all',
            'referrer' => $params->getReferrer(),
            'initialPage' => $params->getPageNum(),
            'queryStringPage' => Querystring::PAGE_NUMBER,
            'queryStringReferrer' => Querystring::REFERRER,
            'queryStringTopic' => Querystring::TOPIC_SLUG,

            /* For configuring the rss button which changes per topic chosen
             */
            'key' => $this->getUser()->getKey(),
        ];

        $sidebar = new Sidebar($this->newsMapper, $this->getUser(), $this->getRequest()->query['page']);

        $data = array_merge($data, $sidebar->generate());

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@commsNews/index.twig', $data)
                ->setTitle('News Feed')
                ->setTab('news')
        );
    }


    /**
     * Get a list of articles that are audienced to me from anywhere and are published and not expired
     * This is only returns data as JSON
     *
     * @api GET /news/lists/feed
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws InvalidArgumentException
     * @throws \DomainException
     */
    public function getListForIndex() :void
    {
        $commentCounts = [];

        $articleIds = (new Lists\Index(
            0,
            $this->topicMapper,
            $this->newsMapper,
            $this->getConfigLoader()->get(ConfigMapper::_READ_MORE),
            $this->getConfigLoader()->get(ConfigMapper::_SAVE_FOR_LATER),
            $this->saveForLaterMapper,
            $this->statisticsMapper,
            $this->statisticsCumulativeMapper,
            $this->databaseInternal,
            $this->articleResolver,
            $this->commentMapper,
            $this->audienceMapper,
            $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED)
        ))->find(new Querystring([]), $this->getUser());

        $articles = $this->newsMapper->findByColumn('id', $articleIds, ['order'=>['publish_at'=>'DESC']]);

        $map = [
            'id',
            'title',
            'blurb',
            'featureImage',
            'author' => [
                'fullname'
            ],
            'publishAt' => [
                'long',
                'relativeTime',
                'json',
            ],
            'topics' => [
                'name',
                'slug',
                'sequence'
            ]
        ];

        // Link auth requires hydrated audiences
        $this->hydrateArticleAudiences($articles);

        $articles->reindex();

        /**
         * If comment module is enabled, load comment counts by thread ids
         */
        if ($this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED)) {
            $commentCounts = $this->commentMapper->getCommentCountByThreadIds(
                Helper::getThreadIdsFromCollection($articles),
                $this->commentMapper::THREAD_ID
            );
        }

        $articles = $articles->jsonSerialize(
            new PropertyDecorator(
                $map,
                new LinkDecorator($this->router, $this->authorizer, $this->getUser()),
                new BlurbDecorator($this->getConfigLoader()->get(ConfigMapper::_READ_MORE)),
                new MetadataDecorator($commentCounts)
            )
        );

        $this->getResponse()->content->set($this->buildJsonOutput($articles));
    }


    /* Archive  */

    /**
     * News Archive
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
     * @throws Exception
     * @throws Unauthorized
     * @api   GET /news/archive{/year,month}
     * @since 18.1
     */
    public function getArchive(int $year = null, int $month = null): void
    {
        $pageTitle = 'News Archive';

        /* These referrers should be used, even when they're not explicitly provided in the url
         */
        $params = new Querystring($this->getRequest()->query);

        if ($params->getFolderId()) {
            if (Access::getInstance()->getFolderAccess($this->getUser(), $params->getFolderId()) < UserAccess::ACCESS_READ) {
                throw new Unauthorized();
            }
        }

        $params->setReferrer(Statistics\Mapper::REFERRER_ARCHIVE);

        $topics = $this->topicMapper->findAll(['order'=>['sequence'=>'ASC']]);

        /* The initial data set to load, given the params provided
         * Compact Mode is hardcoded to be disabled from this context
         * HidePaginator is always false in this context
         */
        $urlPrefix = $this->getRouter()->generate('commsNews.getArchive', ['year' => $year, 'month' => $month]);

        // Find the years and months that have any articles (some may not contain any visible articles; thats ok)
        $availableYearsAndMonths = $this->newsMapper->findYearsAndMonthsWithArticles();

        // Default to filtering by the most recent year if none is provided
        if (null === $year) {
            $year = array_keys($availableYearsAndMonths)[0];
        }

        $partialData = $this->generatePartialForArchive($urlPrefix, $params, $topics, false, $year, $month);

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
            'page' => $this->getRequest()->query['page'],
            'pageWithoutParams' => $this->getRouter()->generate('commsNews.getArchive'),

            /* Archive-specific vars that don't appear anywhere else
             */
            'availableYearsAndMonths' => $availableYearsAndMonths,
            'year' => $year,
            'month' => $month,
            'newsItems' => $partialData,
        ];

        if ($params->getFolderId()) {
            $details = $this->folderMapper->findCheaplyById((int)$params->getFolderId())->findValueLabelPair();

            $metaDetails = [
                'folderId'    => $details['value'],
                'folderName'  => $details['label'],
                'componentId' => $params->getComponentId(),
            ];

            $data = array_merge($data, $metaDetails);

            $pageTitle = $details['label'];
        }

        $sidebar = new Sidebar($this->newsMapper, $this->getUser(), $this->getRequest()->query['page']);

        $data = array_merge($data, $sidebar->generate());

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@commsNews/archive.twig', $data)
                ->setTab('news')
                ->setTitle($pageTitle)
        );
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
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws \PDOException
     * @throws \UnderflowException
     * @return array
     */
    private function generatePartialForArchive(string $urlPrefix, Querystring $params, Collection $topics, bool $hidePaginator, ?int $year, ?int $month): array
    {
        $configuredSearchLimit = $this->getConfigLoader()->get('search_type_limit');
        $searchLimit           = null === $configuredSearchLimit ? self::DEFAULT_SEARCH_LIMIT : (int)$configuredSearchLimit;

        $topicData = (new Lists\Archive(
            $searchLimit,
            $this->topicMapper,
            $this->newsMapper,
            $this->getConfigLoader()->get(ConfigMapper::_READ_MORE),
            $this->getConfigLoader()->get(ConfigMapper::_SAVE_FOR_LATER),
            $this->saveForLaterMapper,
            $this->statisticsMapper,
            $this->statisticsCumulativeMapper,
            $this->databaseInternal,
            $this->articleResolver,
            $this->commentMapper,
            $this->audienceMapper,
            $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED)
        ))->find(
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
            'compactMode' => true,
            'pageNum' => $params->getPageNum(),
            'referrer' => $params->getReferrer()
        ]);

        $this->hydrateArticleAudiences($partialData['articles']);

        return $partialData;
    }


    /**
     * This is now used only in News Archive with a topic
     * This is the central piece of the puzzle
     *
     * @api GET /news/list
     * @since 18.1
     *
     * @deprecated Article lists should now be done on the front-end, with an XHR calls
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
    public function getListByTopic(?int $year, ?int $month): void
    {
        $params = new Querystring($this->getRequest()->query);
        $topics = $this->topicMapper->findAll(['order'=>['sequence'=>'ASC']]);

        /* The requested data set to load, given the params provided
         */
        if ($year) {
            $urlPrefix = $this->getRouter()->generate('commsNews.getArchive', [ 'year' => $year, 'month' => $month ]);
        } else {
            $urlPrefix = $this->getRouter()->generate('commsNews.getIndex');
        }

        $partialData = $this->generatePartialForArchive($urlPrefix, $params, $topics, $params->getHidePaginator(), $year, $month);

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


    /* Created By Me */

    /**
     * Index of articles you've authored
     *
     * @api GET /news/me
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getAuthored()
    {
        $params = new Querystring($this->getRequest()->query);
        $pageNum = $params->getPageNum();
        $status = $params->getStatus();

        if (!$status) {
            $status = 'All';
        }

        $sidebar = new Sidebar($this->newsMapper, $this->getUser(), $this->getRequest()->query['page']);

        $data = array_merge([
            'pageNum' => $pageNum,
            'status' => $status,
        ], $sidebar->generate());

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@commsNews/authored.twig', $data)
                ->setTab('news')
                ->setTitle('My News'));
    }


    /**
     * Get a list of articles that the logged in user authored
     *
     * @api GET /news/me/articles
     * @throws UnexpectedValueException
     */
    public function getAuthoredArticles()
    {
        $args = [
            'order' => [
                'created_at' => 'desc'
            ]
        ];

        $articles = $this->newsMapper->findByColumn('author_id', $this->getUser()->getId(), $args);

        $map = [
            'id',
            'title',
            'blurb',
            'featureImage',
            'publishAt' => [
                'long',
                'relativeTime',
                'json',
            ],
            'topics' => [
                'name',
                'slug',
            ],
            'status',
            'statusDate' => [
                'long',
                'relativeTime',
                'json',
            ]
        ];

        $this->hydrateArticleAudiences($articles);

        $articles->reindex();
        $articles = $articles->jsonSerialize(
            new PropertyDecorator($map, new LinkDecorator($this->router, $this->authorizer, $this->getUser()), new BlurbDecorator($this->getConfigLoader()->get(ConfigMapper::_READ_MORE)))
        );

        $this->getResponse()->content->set($this->buildJsonOutput($articles));
    }


    /* Utility methods */

    /**
     * Generate article's audience segments with audience filtering based on the account access permission.
     * If the logged in user is a member of a staff, filtering will be skipped.
     *
     * @param Model $article
     * @param boolean $hideIndividualUsers
     * @return array
     * @throws MapperException\InsufficientHydrationException
     * @throws UnexpectedValueException
     * @throws UnknownEntityTypeException
     */
    private function getAudienceSegments(Model $article, $hideIndividualUsers = false) :array
    {
        $audienceSegments = [];

        $codec = new Audience\Segment\Codec();

        $translator = new Audience\Segment\Translator(
            $this->databaseInternal,
            $this->strategyFactory
        );
        $translator->buildCache($article);

        foreach ($article->getAudience() as $segment) {

            if (($hideIndividualUsers && $segment->getEntityType() === Audience\Segment\Strategy\User::getEntityType())
                || !$this->isAudienceVisibleToUser($segment, $this->getUser())) {

                continue;
            }

            // Selectize will use 'value' and 'label' to index and display selected items in default rendering.
            // Labels are used in brick rendering
            $option = new Audience\SegmentSelectizeOption($codec->encode($segment), $translator->getSegmentName($segment));

            // We must include the friendly name for each value so that bricks can display extra meta about their component segments.
            if ($segment->isSimple()) {
                $encodedValue = $codec->encodeValue($segment->getEntityValue());

                $option->addLabel($segment->getEntityType(), $encodedValue, $translator->getSegmentName($segment));
            } else {
                foreach ($segment->getChildren() as $child) {
                    $encodedValue = $codec->encodeValue($child->getEntityValue());
                    $option->addLabel($child->getEntityType(), $encodedValue, $translator->getSegmentName($child));
                }
            }

            $audienceSegments[] = $option;
        }

        return $audienceSegments;
    }


    /**
     * @param Audience\Model $segment
     * @param User           $user
     * @return bool
     * @throws MapperException\InsufficientHydrationException
     */
    private function isAudienceVisibleToUser(Audience\Model $segment, User $user): bool
    {
        // Base Case:
        if ($segment->isSimple()) {
            return
                $this->access->getAccessLevel($user, null, UserAccess::SERVICE_NEWS) >= UserAccess::ACCESS_WRITE ||
                $segment->getEntityType() !== Audience\Segment\Strategy\Folder::getEntityType() ||
                Access::getInstance()->getFolderAccess($user, $segment->getEntityValue()) >= UserAccess::ACCESS_READ;
        }

        // Recursive Case:
        foreach ($segment->getChildren() as $childSegment) {
            // If any child is not visible, this composite is not visible
            if (!$this->isAudienceVisibleToUser($childSegment, $user)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Compute the recipients so author can identify
     *
     * @api /news/:articleId/recipients
     * @since 18.1
     *
     * @param Model $article
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getRecipients(Model $article): void
    {
        $userIds = $this->userResolver->findUsersForArticle($article);

        if (\count($userIds)) {
            $users = $this->userMapper->findByColumn('id', $userIds)->sortBy('lastname', 'asc');
        } else {
            $users = $this->userMapper->createCollection();
        }

        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@commsNews/recipients.twig', ['users' => $users]));
    }


    /**
     * Returns the set of audience type strategies which the session user is permitted to audience to
     *
     * @param bool $bypassAudienceTypeFiltering
     * @return array
     * @throws UnknownEntityTypeException
     * @throws UnexpectedValueException
     */
    private function getAvailableAudienceTypeStrategies(bool $bypassAudienceTypeFiltering = false): array
    {
        $availableStrategies = [];
        foreach ($this->strategyFactory->makeAllSimpleStrategies() as $strategy) {
            if ($bypassAudienceTypeFiltering || $strategy->canAudienceToEntityType($this->getUser())) {
                $availableStrategies[] = $strategy::getEntityType();
            }
        }
        return $availableStrategies;
    }

    /**
     *
     * @param bool $generateAjaxRoutes
     * @return array
     * @throws RouteNotFound
     */
    private function getEntityTypesData(bool $generateAjaxRoutes = true): array
    {
        $entityTypes = [
            'campus' => [
                'name'          =>  'Campus',
                'namePlural'    =>  'Campuses',
                'icon'          =>  'location',
                'selfModifier'  =>  'Users in campus',
                'hideFromModal' =>  false,
                'isMulti'       =>  true,
            ],
            'role' => [
                'name'          =>  'Role',
                'namePlural'    =>  'Roles',
                'icon'          =>  'staff-students',
                'selfModifier'  =>  'Users in role',
                'hideFromModal' =>  false,
                'isMulti'       =>  false,
            ],
            'school_house' => [
                'name'          =>  'House',
                'namePlural'    =>  'Houses',
                'icon'          =>  'home',
                'selfModifier'  =>  'Users in role',
                'hideFromModal' =>  false,
                'isMulti'       =>  false,
            ],
            'year' => [
                'name'          =>  'Year Level',
                'namePlural'    =>  'Year Levels',
                'icon'          =>  'teacher',
                'selfModifier'  =>  'Users in year level/s',
                'hideFromModal' =>  false,
                'isMulti'       =>  false,
            ],
            'folder' => [
                'name'          =>  'Class/Group',
                'namePlural'    =>  'Classes & Groups',
                'icon'          =>  'group',
                'selfModifier'  =>  'Class/group members',
                'hideFromModal' =>  false,
                'isMulti'       =>  true,
            ],
            'user' => [
                'name'          =>  'User',
                'namePlural'    =>  'Users',
                'icon'          =>  'user',
                'selfModifier'  =>  'Users',
                'hideFromModal' =>  true,
                'isMulti'       =>  false,
            ],
        ];
        if ($generateAjaxRoutes) {
            foreach ($entityTypes as $type => $entityType) {
                $entityTypes[$type]['ajaxRoute'] = $this->router->generate('commsNewsAudienceBrowser.getList', ['entityType' => $type]);
            }
        }
        return $entityTypes;
    }


    /**
     * Updates an article's attributes with post data payload
     *
     * @param Model $article
     * @param array $postData
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Resource\Exception
     */
    private function updateArticleAttributes(Model $article, array $postData): void
    {
        $article
            ->setTitle($postData['title'])
            ->setBody($postData['body'])
            ->setBlurb($postData['blurb'])
            ->setTopics($this->topicMapper->findByColumn('newsTopicID', $postData['topics']));

        // Only set comments allowed if the value is passed in the form,
        // and the feature is enabled
        if (isset($postData['commentsAllowed']) && $this->getConfigLoader()->get('comms_comments_enabled')) {
            $article->setCommentsAllowed((bool)$postData['commentsAllowed']);
        }

        // Exception thrown when an invalid date is submitted; use last known value instead
        // This can occur with bad input; or when datepicker is still processing strtotime shuriken
        try {
            $article->setPublishAt(\Alaress_Date_Format::newLocal($postData['publishAt']));
        } catch (InvalidArgumentException $e) {
            // noop
        }
        try {
            $article->setExpiresAt(\Alaress_Date_Format::newLocal($postData['expiresAt']));
        } catch (InvalidArgumentException $e) {
            // noop
        }

        /* If any Feature Image was provided, remember it
         * Otherwise clear it (remove a Feature Image)
         */
        if (\is_array($postData['attachment'])) {
            /** @var Storage\Model $featureImage */
            $featureImage = (new CollectorFactory())->build($postData['attachment'] ?: [])->getFirst();
            $article->setFeatureImage($featureImage);
        } else {
            $article->setFeatureImage(null);
        }
    }


    /**
     * @param Model                              $article
     * @param string                             $selectizeAudienceSegments
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     * @throws \DomainException
     */
    private function updateArticleAudience(Model $article, string $selectizeAudienceSegments): void
    {
        $codec = new Audience\Segment\Codec();
        $article->setAudience($codec->decodeSelectizeValues($selectizeAudienceSegments));
        foreach ($article->getAudience() as $segment) {
            $segment->setCommsNewsArticle($article);
        }
    }


    /**
     * Validates a proposed article and audience
     *
     * @param Model $article
     * @throws UnexpectedValueException
     * @throws UnknownEntityTypeException
     * @throws UnsatisfiedException
     */
    private function validateArticleAndAudience(Model $article): void
    {
        // Don't validate drafts or rejected articles.
        if ($article->isDraft() || $article->isRejected()) {
            return;
        }

        // Validate article + authenticate proposed audience
        $validSubmittedArticleSpec = $this->generateValidSubmittedArticleSpec();
        if (!$validSubmittedArticleSpec->isSatisfiedBy($article)) {
            throw new UnsatisfiedException($article, $validSubmittedArticleSpec, "Article audience appears to be invalid");
        }

        // Validate the audience segments
        if ($article->getAudience()->count()) {
            $validSegmentSpecFactory = new Audience\Validation\Factory(
                $this->strategyFactory->getValidRelationshipModifiers(),
                $this->strategyFactory::getValidNullEntities()
            );
            $validSegmentSpec        = $validSegmentSpecFactory->makeValidSegmentSpecification();
            foreach ($article->getAudience() as $segment) {
                if (!$validSegmentSpec->isSatisfiedBy($segment)) {
                    throw new UnsatisfiedException($article, $validSubmittedArticleSpec, "Audience segment appears to be invalid");
                }
            }
        }
    }

    /**
     * Gets return url from query string and history.
     * Returns value of returnUrl query string, if set.
     * If not and if it is visited from the following:
     *    - homepage
     *    - dashboard
     *    - moderation page
     *    - saved page
     * returns the encoded url of the page.
     * Returns null otherwise.
     * @return null|string
     */
    private function getExplicitReturnUrl(): ?string
    {
        $returnUrl = $this->getRequest()->query->get(Querystring::RETURN_URL);
        if ($returnUrl) {
            return $returnUrl;
        }
        // visible url of the previous page.
        $previousUrl = $this->getSession()->getHistory();
        // urls of comms article list that current action can be redirected to - dashboard as the default.
        $commsListUrls = ['/', '/dashboard'];
        // urls which are for ajax call and need to be excluded
        // (we might want to improve getHistory() at some point...)
        $asyncUrls = [];
        try {
            // if the previous url is for ajax call, get one before.
            $ajaxRoutes = [
                'commsNews.getListForIndex',
                'commsNews.getAuthoredArticles'
            ];
            foreach ($ajaxRoutes as $ajaxRoute) {
                $route = $this->getRouter()->generate($ajaxRoute);
                if ($route) {
                    $asyncUrls[] = $route;
                }
            }
            if (\in_array($previousUrl, $asyncUrls, false)) {
                $previousUrl = $this->getSession()->getHistory(2);
            }

            // populate urls of comms article list that current action can be redirected to.
            $commsRoutes = [
                'commsNews.getAuthored',
                'commsNewsModeration.getList',
                'commsNewsSaveForLater.getList',
                'commsNews.getIndex',
            ];
            foreach ($commsRoutes as $commsRoute) {
                $route = $this->getRouter()->generate($commsRoute);
                if ($route) {
                    $commsListUrls[] = $route;
                }
            }
        } catch (RouteNotFound $e) {
            // should not happen
            return null;
        }

        // If the url of the previous pages matches any of the comms list pages above or homepage, return the url
        // null otherwise.
        if (\in_array($previousUrl, $commsListUrls, false)
            || \strpos($previousUrl, '/homepage') !== false) {
            return Querystring::encode($previousUrl);
        }
        return null;
    }


    /**
     * @return \Alaress\Specification\ISpecification
     */
    private function generateValidSubmittedArticleSpec() :\Alaress\Specification\ISpecification
    {
        return (new Factory($this->strategyFactory))->buildSpecCanBeSubmitted($this->getUser());
    }


    /**
     * Convenience helper to hydrate the articles' audiences
     *
     * @param GenericCollection|Model[] $articles
     * @throws UnexpectedValueException
     */
    private function hydrateArticleAudiences(GenericCollection $articles): void
    {
        $articles->indexByCallback(static function (Model $article) {
            return $article->getId();
        });
        $audiencesPerArticle = $this->audienceMapper
            ->findForArticles($articles->keys())
            ->groupByCallback(static function (Audience\Model $segment) {
                return $segment->getCommsNewsArticle()->getId();
            });
        foreach ($articles as $articlesId => $article) {
            $article->setAudience(
                $audiencesPerArticle->keyExists($articlesId)
                    ? $audiencesPerArticle->getOffset($articlesId)
                    : new GenericCollection(Audience\Model::class)
            );
        }
    }
}
