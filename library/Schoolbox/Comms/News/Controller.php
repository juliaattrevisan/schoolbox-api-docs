<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Json\Decorator\PropertyDecorator;
use Alaress\Specification\Exception\UnsatisfiedException;
use Alaress\Web\Controller\Base;
use Alaress\Web\Output\ThemedHtml;
use Aura\Router\Exception\RouteNotFound;
use InvalidArgumentException;
use LogicException;
use PDOException;
use Schoolbox\Application\Exception\AccessDenied;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Discussion\ThreadLocator;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\Comms\News\Lists\UserFeed;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Comms\News\Statistics\Interactions;
use Schoolbox\Comms\News\StickyStatus\ValidChangeStickyStatus;
use Schoolbox\Comms\News\Validation\Factory;
use Schoolbox\Comms\News\Moderation\Controller as ModerationController;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Discussion\Thread;
use Schoolbox\GenericCollection;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Comms\News\Topic;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Storage;
use UnderflowException;
use UnexpectedValueException;
use Schoolbox\User;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;

use function array_keys;
use function array_merge;

/**
 * Class Controller
 *
 * @package Schoolbox\Comms\News
 */
class Controller extends Base
{
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
     * @param Audience\Segment\StrategyFactory $strategyFactory
     * @param Access                           $access
     * @param Thread\Mapper                    $threadMapper
     * @param CommentMapper                    $commentMapper
     * @param File\Mapper                      $fileMapper
     * @param ValidChangeStickyStatus          $validChangeStickyStatus Spec pattern for if sticky status may be changed
     * @param RosettaStone                     $l10n
     * @param EditorTemplateHelper             $editorTemplateHelper
     */
    public function __construct(
        private Mapper $newsMapper,
        private Topic\Mapper $topicMapper,
        private SaveForLater\Mapper $saveForLaterMapper,
        private Statistics\Mapper $statisticsMapper,
        private Statistics\Cumulative\Mapper $statisticsCumulativeMapper,
        private Audience\Mapper $audienceMapper,
        private Folder\Mapper $folderMapper,
        private IConnector $databaseInternal,
        private Audience\ArticleResolver $articleResolver,
        private Audience\Segment\StrategyFactory $strategyFactory,
        private Access $access,
        private Thread\Mapper $threadMapper,
        private CommentMapper $commentMapper,
        private File\Mapper $fileMapper,
        private ValidChangeStickyStatus $validChangeStickyStatus,
        private RosettaStone $l10n,
        private EditorTemplateHelper $editorTemplateHelper,
    ) {
    }

    /* CRUD actions */

    /**
     * Provide form to create a news article
     *
     * @api   GET /news/create
     * @since 18.1
     *
     * @throws MapperException
     * @throws UnexpectedValueException
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
            ->setMimeFilter('image/*')
            ->setFilesystemContext('images');

        /* Misc attached files
         */
        $fileZone = (new Zone('fileImage'))
            ->setFileVar('attachedFiles[]')
            ->setMulti(true)
            ->setAttachPrompt('Attach files')
            ->setAttachmentsLabel('');

        // Prefill audience
        $audienceSegments = [];
        $params = new Querystring($this->requestQuery);
        $folderId = $params->getFolderId();
        $entityType = $params->getEntityType();

        // Hard-coded to folder (for now)
        if ($folderId && $entityType && $entityType === Audience\Segment\Strategy\Folder::getEntityType()) {

            /** @var Audience\Segment\Strategy\Folder $folderStrategy */
            $folderStrategy = $this->strategyFactory->makeSimpleStrategy($entityType);

            // Ensure the user is permitted to target this entity type and has the permissions to audience into the folder
            if (
                $folderStrategy->canAudienceToEntityType($this->getUser()) &&
                (Access::getInstance()->getFolderAccess($this->getUser(), (int)$folderId) >= UserAccess::ACCESS_WRITE ||
                    $folderStrategy->canAudienceToAnyClassOrGroup($this->getUser()))
            ) {
                $codec = new Audience\Segment\Codec();
                $label = $this->folderMapper->findCheaplyById($folderId)->getNameWithCodes();

                $audienceSegment = new Audience\SegmentSelectizeOption(
                    $codec->encodeSimpleAttributes($entityType, (string)$folderId, Audience\Model::RELATIONSHIP_SELF),
                    $label
                );

                $audienceSegment->addLabel($entityType, $codec->encodeValue((string)$folderId), $label);
                $audienceSegments[] = $audienceSegment;
            }
        }

        $this->response = $this->responseFromTemplate('@commsNews/create.twig', [
            'action'                => 'Create',
            'article'               => $article,
            'featureImageZone'      => $zone->getTwigContext(),
            'fileZone'              => $fileZone->getTwigContext(),
            'isAutoSavable'         => true,
            'encodedSegments'       => $audienceSegments,
            'availableEntityTypes'  => $this->getAvailableAudienceTypeStrategies(),
            'returnUrl'             => $this->getExplicitReturnUrl(),
            'entityTypesData'       => $this->getEntityTypesData(),
            'mayChangeStickyStatus' => $this->validChangeStickyStatus->isSatisfiedBy($this->getUser()),
            'editorTemplates'       => $this->editorTemplateHelper->getTemplatesForCategory(),
        ], "Create {$this->getDi()->get('sitePage')->getLocalisedTerm('news', false, true)}", 'news');
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
        $params = new Querystring($this->requestQuery);
        $params->setFolderId($folderId);
        $params->setEntityType(Audience\Segment\Strategy\Folder::getEntityType());
        $params->setReturnUrl(Querystring::encode('/homepage/' . $folderId));

        $this->response = $this->redirectFound(
            $this->routeGenerator->generate('commsNews.getCreate') . '?' . $params
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
     * @throws PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws UnexpectedValueException
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
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @throws UnknownEntityTypeException
     */
    public function getUpdate(Model $article): void
    {
        /* Feature image
         */
        $zone = (new Zone('featureImage'))
            ->setMulti(false)
            ->setAttachPrompt('Attach a feature image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*')
            ->setFilesystemContext('images');

        if ($article->getFeatureImage()) {
            $zone->attachFiles([$article->getFeatureImage()]);
        }

        /* Misc attached files
         */
        $fileZone = (new Zone('file'))
            ->setFileVar('attachedFiles[]')
            ->setMulti(true)
            ->setAttachPrompt('Attach files')
            ->setMimeFilter('*/*')
            ->setAttachmentsLabel('');

        if (null !== $article->getAttachedFiles() && $article->getAttachedFiles()->count()) {
            $fileZone->attachFiles($article->getAttachedFiles()->indexBy('hash')->keys());
        }

        $this->response = $this->responseFromTemplate('@commsNews/create.twig', [
            'action'                => 'Update',
            'article'               => $article,
            'featureImageZone'      => $zone->getTwigContext(),
            'fileZone'              => $fileZone->getTwigContext(),
            'encodedSegments'       => $this->getAudienceSegments(article: $article),
            'isAutoSavable'         => !$article->isSubmitted() || $article->isRejected(),
            'returnUrl'             => $this->getExplicitReturnUrl(),
            'availableEntityTypes'  => $this->getAvailableAudienceTypeStrategies(),
            'entityTypesData'       => $this->getEntityTypesData(),
            'mayChangeStickyStatus' => $this->validChangeStickyStatus->isSatisfiedBy($this->getUser()),
            'editorTemplates'       => $this->editorTemplateHelper->getTemplatesForCategory(),
        ], "Update {$article->getTitle()}", 'news');
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
     * @throws PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws UnknownEntityTypeException
     * @throws InvalidArgumentException
     */
    public function postUpdate(Model $article): void
    {
        $data = $this->requestPost->getArrayCopy();

        // Update article with proposed attributes
        $this->updateArticleAttributes($article, $data);

        // Update article with proposed audience. Cache existing audience for later efficient persistence
        $existingAudience = $article->getAudience()->shallowClone();

        if (!$article->isApproved()) {
            $this->updateArticleAudience($article, $data['audience']);
        }

        // Validate proposed article + audience
        // (This protects against DOM manipulation + logic bugs, it should not be shown on normal work-flows)
        try {
            $this->validateArticleAndAudience($article);
        } catch (UnsatisfiedException $e) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => $e->getMessage(),
            ])->withStatus(400);

            return;
        }

        // Persist article
        $this->newsMapper->save($article);
        $this->newsMapper->saveFiles($article);

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
        if ($this->isXhrRequest()) {
            // Send the update page route, JS will update the window state to this
            $url = $this->routeGenerator->generate('commsNews.getUpdate', $routeParams);
            if (isset($data[Querystring::RETURN_URL])) {
                $url .= '?' . http_build_query([Querystring::RETURN_URL => $data[Querystring::RETURN_URL]]);
            }

            // Send all the possible routes, JS will determine which ones to show where
            $updateRoute = $this->routeGenerator->generate('commsNews.postUpdate', $routeParams);
            $submitRoute = $this->getRouteIfAuthorized('commsNewsModeration.postSubmit', $routeParams);
            $approveRoute = false;
            if ($article->canBeApproved()) {
                $approveRoute = $this->getRouteIfAuthorized('commsNewsModeration.postApprove', $routeParams);
            }

            $this->response = $this->responseAsJson([
                'success'      => true,
                'articleId'    => $article->getId(),
                'url'          => $url,
                'returnUrl'    => $data[Querystring::RETURN_URL] ?? null,
                'updateRoute'  => $updateRoute,
                'submitRoute'  => $submitRoute,
                'approveRoute' => $approveRoute
            ]);
        } else {
            /* Otherwise, you're the creator and you're editing this thing normally
             * Go to the detail screen to see the outcome of your changes
             * and also use that screen to advance to the next step
             */
            if ($article->isPublished() && isset($data[Querystring::RETURN_URL])) {
                $nextStep = Querystring::decode($data[Querystring::RETURN_URL]);
            } else {
                $nextStep = $this->routeGenerator->generate('commsNews.getDetail', $routeParams);
                if (isset($data[Querystring::RETURN_URL])) {
                    $nextStep .= '?' . http_build_query([Querystring::RETURN_URL => $data[Querystring::RETURN_URL]]);
                }
            }
            $this->response = $this->redirectAfterPost($nextStep);
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
     * @throws PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws UnexpectedValueException
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

            if ($this->requestQuery->get(Querystring::RETURN_URL)) {
                $nextStep = Querystring::decode($this->requestQuery->get(Querystring::RETURN_URL));
            } else {
                $nextStep = $this->routeGenerator->generate('commsNews.getIndex');
            }

            if ($this->isXhrRequest()) {
                $this->response = $this->responseAsJson([
                    'success' => true,
                    'redirect' => $nextStep
                ]);
            } else {
                $this->response = $this->redirectFound($nextStep);
            }
        } catch (MapperException | RouteNotFound | \Alaress\Database\Predicate\InvalidColumnException | PDOException | DefinitionException | UnexpectedValueException | Exception $e) {
            if ($this->isXhrRequest()) {
                $this->response = $this->responseAsJson([
                    'success' => false,
                    'message' => $e->getMessage()
                ])->withStatus(400);

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
        $this->response = $this->responseAsJson($this->topicMapper->findAll());
    }


    /**
     * Load a news article so you can read its contents (the detail screen)
     *
     * @api   GET /news/{articleId}
     * @since 18.1
     *
     * @param Model $article
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws PDOException
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
        $referrer = $this->requestQuery->get(Querystring::REFERRER);

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
            $map[] = 'attachedFiles';

            $data = [
                'article'         => $article->jsonSerialize(
                    new PropertyDecorator(
                        $map,
                        new LinkDecorator($this->routeGenerator, $this->authorizer, $this->getUser()),
                        new FileDecorator(new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfigLoader())))
                    )
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
                'encodedSegments'       => $this->getAudienceSegments(article: $article, hideIndividualUsers: true, checkFolderAccess: true),
                'availableEntityTypes'  => $this->getAvailableAudienceTypeStrategies(true),
                'entityTypesData'       => $this->getEntityTypesData(false),
                'isPublishable'         => $this->generateValidSubmittedArticleSpec()->isSatisfiedBy($article),
                'policies'  => [
                    'moderationEnabled'     => $this->getConfigLoader()->get(ConfigMapper::_GLOBAL_MODERATION),
                    'commentsIsEnabled'     => $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED),
                ],
            ];
        }

        $this->response = $this->responseDependentOnRequest(
            '@commsNews/detail.twig',
            $data,
            $article->getTitle(),
            'news',
        );
    }


    /* Homepage */

    /**
     * Get a list of articles that are audienced to me for homepage component
     *
     * @api GET /news/lists/homepage/{folderId}
     *
     * @param int $folderId The folder to which the articles are audienced
     *
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws PDOException
     * @throws InvalidArgumentException
     * @throws UnderflowException
     */
    public function getListForHomepage(int $folderId): void
    {
        $params = new Querystring($this->requestQuery);
        $params->setFolderId($folderId);

        $partialData = $this->generatePartialForHomepage($params);
        // homepage student view
        $partialData['readonly'] = $this->requestQuery->get('readonly') ?? false;

        if (!$this->isXhrRequest()) {
            // if it's NOT XHR, go for API version
            $this->response = $this->responseAsJson($partialData['articles']);
            return;
        }

        $content = $this->responseFromTemplateUnthemed('@commsNews/partial.twig', $partialData)->getBody()->getContents();

        $this->response = $this->responseAsJson([
            'success' => true,
            'fragment' => $content
        ]);
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
     * @throws PDOException
     * @throws InvalidArgumentException
     * @throws UnderflowException
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
            $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED),
            $this->fileMapper
        ))->find(
            $params,
            $this->getUser()
        );

        if (\count($topicData['articles']) < 1) {
            $showArchiveNewsLink = $this->newsMapper->checkArchiveForHomepage($params->getFolderId());
        }

        return array_merge($topicData, [
            'hidePaginator' => true,
            'compactMode' => $params->getCompactMode(),
            'referrer' => $params->getReferrer(),
            'folderId' => $params->getFolderId(),
            'showArchiveNewsLink' => $showArchiveNewsLink,
            'componentId' => $params->getComponentId(),
            'fallbackImage' => [
                'type' => $this->getConfigLoader()->get('comms_news_article_list_fallback_image'),
                'hash' => $this->getConfigLoader()->get('skin_logo_large')
            ]
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
     */
    public function getIndex(): void
    {
        /* These referrers should be used, even when they're not explicitly provided in the url
         */
        $params = new Querystring($this->requestQuery);
        $params->setReferrer(Statistics\Mapper::REFERRER_INDEX);

        /* Merge a whole bunch of other settings too
         * Specifically applies to the index page which loads slightly differently than component variant
         */
        $data = [
            /* Core and querystring params
             */
            'queryStringPage' => Querystring::PAGE_NUMBER,
            'queryStringReferrer' => Querystring::REFERRER,
            'queryStringTopic' => Querystring::TOPIC_SLUG,

            /* Topics
             */
            'topicAll' => [
                'name' => 'All',
                'slug' => Mapper::TOPIC_ALL_SLUG,
            ],
            'topic' => $params->getSlug() ?: 'all',

            /* Referrers and Redirects
             */
            'referrer' => $params->getReferrer(),

            /* Layout
             */
            'itemLayout' => $this->getConfigLoader()->get('comms_news_article_list_layout'),
            'fallbackImage' => $this->getConfigLoader()->get('comms_news_article_list_fallback_image'),

            /* Pagination
             */
            'initialPage' => $params->getPageNum(),

            /* For configuring the rss button which changes per topic chosen
             */
            'key' => $this->getUser()->getKey(),
        ];

        $sidebar = new Sidebar($this->newsMapper, $this->getUser(), RequestUriParser::parseUrlPath($this->request->getServerParams()));

        $data = array_merge($data, $sidebar->generate());

        $this->response = $this->responseFromTemplate('@commsNews/index.twig', $data, 'News Feed', 'news');
    }


    /**
     * Get a list of published (not expired) articles that are:
     * * Audienced to session user
     * * Authored by session user, OR
     * * Moderated by session user
     *
     * Returns data as JSON
     *
     * @api          GET /news/lists/feed?topic={topicSlug}
     *
     * @throws InvalidColumnException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws NotFound
     * @noinspection PhpUnused
     */
    public function getListForIndex(): void
    {
        $topicSlug = $this->requestQuery->get(Querystring::TOPIC_SLUG);
        $this->assertValidTopic($topicSlug);

        $userFeed   = new UserFeed($this->newsMapper, $this->articleResolver);
        $articleIds = $userFeed->find($this->getUser(), Model::STATE_PUBLISHED, false);

        $commentCounts = [];
        $attachmentCounts = [];

        if (count($articleIds)) {
            $pb       = $this->newsMapper->getPredicatePublished()->in('id', $articleIds);
            $articles = $this->newsMapper->findByPredicates($pb, ['order' => ['sticky' => 'DESC', 'publish_at' => 'DESC']]);

            /* Attachment counts for each article in this set
             */
            $attachmentCounts = $this->fileMapper->findCounts($articleIds);
        } else {
            $articles = $this->newsMapper->createCollection();
        }

        $map = [
            'id',
            'title',
            'blurb',
            'sticky',
            'featureImage',
            'author' => [
                'fullname'
            ],
            'publishAt',
            'viewedAt',
            'status',
            'statusDate',
            'topics' => [
                'name',
                'slug',
                'sequence'
            ],
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
                new LinkDecorator($this->routeGenerator, $this->authorizer, $this->getUser()),
                new BlurbDecorator(
                    $this->getConfigLoader()->get(ConfigMapper::_READ_MORE),
                    $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED),
                    $attachmentCounts
                ),
                new MetadataDecorator($commentCounts),
                new AttachmentDecorator($attachmentCounts)
            )
        );

        //lets check for article views, and if so add them now
        $statsMapper = \Schoolbox\Comms\News\Statistics\Mapper::getInstance();

        $viewedList = $statsMapper->findByColumnList([
            'article_id' => $articleIds,
            'user_id'    => $this->getUser()->getID()
        ]);

        foreach ($articles as $id => $article) {
            //set the 'default' null to all articles,
            $articles[$id]['viewedAt'] = null;
            //check for if this article has been viewed, if so set its viewedAt date
            foreach ($viewedList as $viewed) {
                if ($viewed->getArticleId() === $article['id']) {
                    $articles[$id]['viewedAt'] = $viewed->getOccursAt();
                    break;
                }
            }
        }

        $this->response = $this->responseAsJson($articles);
    }


    /* Archive  */

    /**
     * News Archive
     *
     * @param int|null $year
     * @param int|null $month
     * @throws AccessDenied
     * @throws DefinitionException
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws LogicException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws MapperException\NotFound
     * @throws NotFound
     * @throws PDOException
     * @throws RouteNotFound
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @api   GET /news/archive{/year,month}
     * @since 18.1
     */
    public function getArchive(?int $year = null, ?int $month = null): void
    {
        $pageTitle = 'News Archive';

        /* These referrers should be used, even when they're not explicitly provided in the url
         */
        $params = new Querystring($this->requestQuery);

        $this->assertValidFolder($params->getFolderId());

        $params->setReferrer(Statistics\Mapper::REFERRER_ARCHIVE);

        $topics = $this->topicMapper->findAll(['order' => ['sequence' => 'ASC']]);

        /* The initial data set to load, given the params provided
         * Compact Mode is hardcoded to be disabled from this context
         * HidePaginator is always false in this context
         */
        $urlPrefix = $this->routeGenerator->generate('commsNews.getArchive', ['year' => $year, 'month' => $month]);

        $this->assertValidTopic($params->getSlug());

        $partialData = $this->generatePartialForArchive($urlPrefix, $params, false, $year, $month, true);

        $availableYearsAndMonths = $partialData['yearsAndMonths'];
        // Default to filtering by the most recent year if none is provided
        if (null === $year && is_array($availableYearsAndMonths) && count($availableYearsAndMonths) > 0) {
            $year = array_keys($availableYearsAndMonths)[0];
        }

        $content = $this->responseFromTemplateUnthemed('@commsNews/partial.twig', $partialData)->getBody()->getContents();

        /* Merge a whole bunch of other settings too
         * Specifically applies to the index page which loads slightly differently than component variant
         */
        $data = [
            'initialContent' => $content,
            'topics' => $topics,
            'topicAll' => [
                'name' => 'All',
                'slug' => Mapper::TOPIC_ALL_SLUG,
            ],
            'slug' => $params->getSlug(),
            'referrer' => $params->getReferrer(),
            'page' => RequestUriParser::parseUrlPath($this->request->getServerParams()),
            'pageWithoutParams' => $this->routeGenerator->generate('commsNews.getArchive'),
            'availableYearsAndMonths' => $availableYearsAndMonths,
            'year' => $year,
            'month' => $month,
            'newsItems' => $partialData,
        ];

        if ($params->getFolderId() !== null) {
            $details = $this->folderMapper->findCheaplyById($params->getFolderId())->findValueLabelPair();

            $metaDetails = [
                'folderId'    => $details['value'],
                'folderName'  => $details['label'],
                'componentId' => $params->getComponentId(),
            ];

            $data = array_merge($data, $metaDetails);

            $pageTitle = $details['label'];
        }

        $sidebar = new Sidebar($this->newsMapper, $this->getUser(), $this->requestQuery->get('page'));

        $data = array_merge($data, $sidebar->generate());

        $this->response = $this->responseFromTemplate(
            '@commsNews/archive.twig',
            $data,
            $pageTitle,
            'news'
        );
    }


    /**
     * Generate a partial fragment containing articles
     * Used in both synchronous and asynchronous requests
     *
     * @param string             $urlPrefix
     * @param Querystring        $params
     * @param bool               $hidePaginator
     * @param int|null           $year
     * @param int|null           $month
     * @param bool               $getYearsAndMonths Whether to include data regarding which years/months have data
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws PDOException
     * @throws UnderflowException
     * @return array
     */
    private function generatePartialForArchive(string $urlPrefix, Querystring $params, bool $hidePaginator, ?int $year, ?int $month, bool $getYearsAndMonths): array
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
            $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED),
            $this->fileMapper
        ))->find(
            $urlPrefix,
            $params,
            $this->getUser(),
            $year,
            $month,
            $getYearsAndMonths
        );

        /* Always provide paginator from this context
         */
        $partialData = array_merge($topicData, [
            'hidePaginator' => $hidePaginator,
            'compactMode' => true,
            'pageNum' => $params->getPageNum(),
            'referrer' => $params->getReferrer(),
            'fallbackImage' => [
                'type' => $this->getConfigLoader()->get('comms_news_article_list_fallback_image'),
                'hash' => $this->getConfigLoader()->get('skin_logo_large')
            ]
        ]);

        $this->hydrateArticleAudiences($partialData['articles']);

        return $partialData;
    }


    /**
     * This is now used only in News Archive with a topic
     * This is the central piece of the puzzle
     *
     * @api          GET /news/list
     * @since        18.1
     *
     * @deprecated   Article lists should now be done on the front-end, with an XHR calls
     *
     * @param int|null $year
     * @param int|null $month
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws NotFound
     * @throws AccessDenied
     * @noinspection PhpUnused
     */
    public function getListByTopic(?int $year, ?int $month): void
    {
        $params = new Querystring($this->requestQuery);

        /* The requested data set to load, given the params provided
         */
        if ($year) {
            $urlPrefix = $this->routeGenerator->generate('commsNews.getArchive', [ 'year' => $year, 'month' => $month ]);
        } else {
            $urlPrefix = $this->routeGenerator->generate('commsNews.getIndex');
        }

        $this->assertValidTopic($params->getSlug());
        $this->assertValidFolder($params->getFolderId());
        $partialData = $this->generatePartialForArchive($urlPrefix, $params, $params->getHidePaginator(), $year, $month, false);

        if (!$this->isXhrRequest()) {
            // if it's NOT XHR, go for API version
            $this->response = $this->responseAsJson([
                'articles' => $partialData['articles']
            ]);

            return;
        }

        $content     = $this->responseFromTemplateUnthemed('@commsNews/partial.twig', $partialData)->getBody()->getContents();
        $viewAllLink = "{$this->getRouteIfAuthorized('commsNews.getIndex')}?topic={$params->getSlug()}";

        $this->response = $this->responseAsJson([
            'success' => true,
            'title' => $this->getDi()->get('l10n')->translate('news', false, true),
            'url' => $partialData['currentUrl'],
            'fragment' => $content,
            'slug' => $params->getSlug(),
            'viewAllLink' => $partialData['articles']->count() ? $viewAllLink : null
        ]);
    }


    /* Created By Me */

    /**
     * Index of articles you've authored
     *
     * @api GET /news/me
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getAuthored()
    {
        $params = new Querystring($this->requestQuery);
        $pageNum = $params->getPageNum();
        $status = $params->getStatus();

        if (!$status) {
            $status = 'All';
        }

        $sidebar = new Sidebar($this->newsMapper, $this->getUser(), RequestUriParser::parseUrlPath($this->request->getServerParams()));

        $data = array_merge([
            'pageNum' => $pageNum,
            'status' => $status,
        ], $sidebar->generate());

        $this->response = $this->responseFromTemplate(
            '@commsNews/authored.twig',
            $data,
            'My News',
            'news'
        );
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
            'publishAt',
            'topics' => [
                'name',
                'slug',
            ],
            'status',
            'statusDate',
        ];

        $this->hydrateArticleAudiences($articles);

        /* Attachment counts for each article in this set
         */
        $attachmentCounts = $this->fileMapper->findCounts($articles->getPropertyValues('id'));

        $articles->reindex();
        $articles = $articles->jsonSerialize(
            new PropertyDecorator(
                $map,
                new LinkDecorator($this->routeGenerator, $this->authorizer, $this->getUser()),
                new BlurbDecorator(
                    $this->getConfigLoader()->get(ConfigMapper::_READ_MORE),
                    $this->getConfigLoader()->get(ConfigMapper::COMMS_COMMENTS_ENABLED),
                    $attachmentCounts
                )
            )
        );

        $this->response = $this->responseAsJson($articles);
    }


    /* Utility methods */

    /**
     * Generate article's audience segments with audience filtering based on the account access permission.
     * If the logged in user is a member of a staff, filtering will be skipped.
     *
     * Severe warning:
     * $checkFolderAccess will nullify an audience segment's value, causing it to be hidden in a ui element.
     * This was intended to be used only on the Detail screen to hide data from certain members of the demographic
     * and you have to be careful on the Update screen where a news author is using the result of this method
     * to populate the audience selectize. Sadly there's a coupling between frontend representation for viewers
     * and authoring representation for authors.
     *
     * @param Model $article
     * @param boolean $hideIndividualUsers
     * @param bool $checkFolderAccess
     * @return array
     */
    private function getAudienceSegments(Model $article, $hideIndividualUsers = false, $checkFolderAccess = false): array
    {
        $audienceSegments = [];

        $codec = new Audience\Segment\Codec();
        $access = Access::getInstance();

        $translator = new Audience\Segment\Translator(
            $this->databaseInternal,
            $this->strategyFactory
        );
        $translator->buildCache($article);

        /* SA-20576
         * This is related to the $checkFolderAccess arg which allows you to check $viewer's folder access
         * to an audiencing segment, conditionally disregarding that segment in specific usages of this method
         */
        $folderEntityType = Audience\Segment\Strategy\Folder::getEntityType();

        foreach ($article->getAudience() as $segment) {
            if (
                ($hideIndividualUsers && $segment->getEntityType() === Audience\Segment\Strategy\User::getEntityType())
                || !$this->isAudienceVisibleToUser($segment, $this->getUser())
            ) {
                continue;
            }

            // store this, so we can reuse and not need to fetch when we clear the id (if the user has no access)
            $segmentName = $translator->getSegmentName($segment);

            /**
             * check if this is a folder, and if the user has access to it
             * if no access, remove the folder id, so we hide the link.
             */
            /* Super-important Steve Notes
             * The Detail screen also uses this method to potentially render a subset of the audiencing.
             * However, the Update screen ALSO USES THIS to prepopulate the audience selectize so you have
             * to be mindful about when to exclude elements and when not to exclude elements. This oddball
             * action-at-a-distance emerged from SA-19147.
             */
            if (
                $checkFolderAccess &&
                $segment->getEntityType() === $folderEntityType &&
                !$access->getFolderAccess($this->getUser(), (int)$segment->getEntityValue())
            ) {
                /* You didn't have access to this folder: pretend this didn't exist.
                 */
                continue;
            }

            // Selectize will use 'value' and 'label' to index and display selected items in default rendering.
            // Labels are used in brick rendering
            $option = new Audience\SegmentSelectizeOption($codec->encode($segment), $segmentName);

            // We must include the friendly name for each value so that bricks can display extra meta about their component segments.
            if ($segment->isSimple()) {
                $encodedValue = $codec->encodeValue($segment->getEntityValue());

                $option->addLabel($segment->getEntityType(), $encodedValue, $segmentName);
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
            try {
                $access = Access::getInstance();
                // do I have direct access to this news audience?
                if (
                    $this->access->getAccessLevel($user, UserAccess::SERVICE_NEWS) >= UserAccess::ACCESS_WRITE ||
                    $segment->getEntityType() !== Audience\Segment\Strategy\Folder::getEntityType() ||
                    $access->getFolderAccess($user, (int)$segment->getEntityValue()) >= UserAccess::ACCESS_READ
                ) {
                    return true;
                }

                // does my child have direct access to this news audience?
                // intentionally ignoring children could have service perms
                foreach ($user->getChildren() as $child) {
                    if (
                        $segment->getEntityType() !== Audience\Segment\Strategy\Folder::getEntityType() ||
                        $access->getFolderAccess($child, (int)$segment->getEntityValue()) >= UserAccess::ACCESS_READ
                    ) {
                        return true;
                    }
                }
            } catch (MapperException\Deleted) {
                return false;
            }
            // neither me, or my child have access to the news audience. DENY
            return false;
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
                'name'          =>  $this->l10n->translate('yearLevel', false, true),
                'namePlural'    =>  $this->l10n->translate('yearLevel', true, true),
                'icon'          =>  'teacher',
                'selfModifier'  =>  sprintf('Users in %s', $this->l10n->translate('yearLevel', true, true)),
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

        /* This is optional and plan-based
         */
        if ($this->getConfigLoader()->get('fixtures_enabled')) {
            $entityTypes['fixturesteam'] = [
                'name' => 'Team',
                'namePlural' => 'Teams',
                'icon' => 'sport',
                'selfModifier' => 'Users in fixtures team',
                'hideFromModal' => false,
                'isMulti' => true,
            ];
        }

        if ($generateAjaxRoutes) {
            foreach ($entityTypes as $type => $entityType) {
                $entityTypes[$type]['ajaxRoute'] = $this->routeGenerator->generate('commsNewsAudienceBrowser.getList', ['entityType' => $type]);
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
     * @throws LogicException
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Resource\Exception
     */
    private function updateArticleAttributes(Model $article, array $postData): void
    {
        $article
            ->setTitle($postData['title'])
            ->setBody($postData['body'] ?? '')
            ->setBlurb($postData['blurb'] ?? '')
            ->setTopics($this->topicMapper->findByColumn('newsTopicID', $postData['topics'] ?? null));

        // Only allow users who may change sticky status to do so
        if ($this->validChangeStickyStatus->isSatisfiedBy($this->getUser())) {
            $article->setSticky((bool)($postData['sticky'] ?? false));
        }

        // Exception thrown when an invalid date is submitted; use last known value instead
        // This can occur with bad input; or when datepicker is still processing strtotime shuriken
        try {
            $article->setPublishAt(\Alaress_Date_Format::newLocal($postData['publishAt'] ?? null));
        } catch (InvalidArgumentException $e) {
            // noop
        }
        try {
            $article->setExpiresAt(\Alaress_Date_Format::newLocal($postData['expiresAt'] ?? null));
        } catch (InvalidArgumentException $e) {
            // noop
        }

        /* If any Feature Image was provided, remember it
         * Otherwise clear it (remove a Feature Image)
         */
        if (\is_array($postData['attachment'] ?? null)) {
            /** @var Storage\Model $featureImage */
            $featureImage = (new CollectorFactory())->build($postData['attachment'] ?: [])->getFirst();
            $article->setFeatureImage($featureImage);
        } else {
            $article->setFeatureImage(null);
        }

        /* Associate any associated files that a user associated with this article
         * These hashes might already exist but with a different filename so in this
         * case we need to remember the filename of this version of the file and not
         * use the one that already existed
         *
         * Process each of attached files separately
         */
        $newList = new Collection();

        if (
            isset($postData['attachedFiles'])
            && \is_array($postData['attachedFiles'])
            && \count($postData['attachedFiles'])
        ) {
            /* These are the task_file relations we already know about
             */
            $existing = $article->getAttachedFiles()->indexBy('hash')->getAll();

            $factory = new CollectorFactory();
            $attachedFiles = $factory->build($postData['attachedFiles'])->getAll();

            /* Exclude any hashes that are no longer in the set
             */
            foreach ($attachedFiles as $attachedFile) {
                /* If this was already in the $article model:
                 *
                 * Reuse the id in the comms_news_article_file table so we don't keep accumulating relations
                 * Get its latest name and update that
                 *
                 * Otherwise insert it as a new comms_news_article_file relationship
                 */
                $articleFile = $existing[$attachedFile->getHash()] ?? new File\Model();

                $articleFile->setStorage($attachedFile)
                    ->setArticle($article)
                    ->setBasename($attachedFile->getBasename())
                    ->setUpdatedAt(\Alaress_Date_Format::now());

                $newList->add($articleFile);
            }
        }

        $article->setAttachedFiles($newList);
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
        $returnUrl = $this->requestQuery->get(Querystring::RETURN_URL);
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
                $route = $this->routeGenerator->generate($ajaxRoute);
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
                $route = $this->routeGenerator->generate($commsRoute);
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
        if (
            \in_array($previousUrl, $commsListUrls, false)
            || \strpos($previousUrl, '/homepage') !== false
        ) {
            return Querystring::encode($previousUrl);
        }
        return null;
    }


    /**
     * @return \Alaress\Specification\ISpecification
     */
    private function generateValidSubmittedArticleSpec(): \Alaress\Specification\ISpecification
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


    /**
     * Asserts that a topic is valid, throws 404 if not found
     *
     * @param string|null $topic
     * @throws NotFound
     */
    private function assertValidTopic(?string $topic): void
    {
        if (!(new ValidTopic($this->topicMapper))->isSatisfiedBy($topic)) {
            throw new NotFound("No topic found with name '$topic'");
        }
    }


    /**
     * Asserts that a folder param is valid, throws 401 if not authorised
     *
     * @param int|null $folderId
     * @throws AccessDenied
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws LogicException
     */
    private function assertValidFolder(?int $folderId): void
    {
        if ($folderId === null) {
            return;
        }

        if (Access::getInstance()->getFolderAccess($this->getUser(), $folderId) === UserAccess::ACCESS_DENY) {
            throw new AccessDenied("User {$this->getUser()->getId()} does not have access to folder $folderId");
        }
    }
}
