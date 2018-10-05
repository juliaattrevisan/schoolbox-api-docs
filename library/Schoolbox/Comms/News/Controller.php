<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Alaress\Database\Connector\IConnector;
use Alaress\Json\Decorator\PropertyDecorator;
use Alaress\Web\Controller\Base;
use Alaress\Web\Output\ThemedHtml;
use Aura\Router\Exception\RouteNotFound;
use InvalidArgumentException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Collection;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\Comms\News\SaveForLater;
use Schoolbox\Comms\News\Statistics\ImpressionsConversionsOpens;
use Schoolbox\Comms\News\Validation\ValidUserCanAudienceTo;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Json\Decorator\LinkDecorator;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\News\Topic;
use Schoolbox\News\TopicMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Resource\Storage;
use UnexpectedValueException;

class Controller extends Base
{

    /**
     * @var Mapper
     */
    private $newsMapper;

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

    private const DEFAULT_SEARCH_LIMIT = 10;


    /**
     * @param Mapper                           $newsMapper
     * @param TopicMapper                      $topicMapper
     * @param SaveForLater\Mapper              $saveForLaterMapper
     * @param Statistics\Mapper                $statisticsMapper
     * @param Statistics\Cumulative\Mapper     $statisticsCumulativeMapper
     * @param Audience\Mapper                  $audienceMapper
     * @param Folder\Mapper                    $folderMapper
     * @param IConnector                       $databaseInternal
     * @param Audience\ArticleResolver         $articleResolver
     * @param Audience\UserResolver            $userResolver
     * @param Audience\Segment\StrategyFactory $strategyFactory
     */
    public function __construct(
        Mapper $newsMapper,
        TopicMapper $topicMapper,
        SaveForLater\Mapper $saveForLaterMapper,
        Statistics\Mapper $statisticsMapper,
        Statistics\Cumulative\Mapper $statisticsCumulativeMapper,
        Audience\Mapper $audienceMapper,
        Folder\Mapper $folderMapper,
        IConnector $databaseInternal,
        Audience\ArticleResolver $articleResolver,
        Audience\UserResolver $userResolver,
        Audience\Segment\StrategyFactory $strategyFactory
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
    }


    /**
     * Provide form to create a news article
     *
     * @api   GET /comms/news/create
     * @level 18.1
     *
     * @throws MapperException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws UnknownEntityTypeException
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
        $groupId = $this->getRequest()->query->get('groupId');
        $entityType = $this->getRequest()->query->get('type');

        // Hard-coded to folder (for now)
        if ($groupId && $entityType && $entityType === Audience\Segment\Strategy\Folder::getEntityType()) {
            $folderStrategy = $this->strategyFactory->makeSimpleStrategy($entityType);
            // Ensure the user is permitted to target this entity type
            if ($folderStrategy->canAudienceToEntityType($this->getUser())) {
                // Ensure the user is permitted to target this entity value
                $availableFolders   = $this->databaseInternal->executeQuery($folderStrategy->getSearchSql($this->getUser()))->fetchAll();
                $availableFolderIds = \array_column($availableFolders, 'id');
                if (\in_array($groupId, $availableFolderIds, false)) {
                    $codec = new Audience\Segment\Codec($this->audienceMapper);
                    $label = $this->folderMapper->findCheaplyById($groupId)->getNameWithCodes();

                    $audienceSegment = new Audience\SegmentSelectizeOption(
                        $codec->encodeSimpleAttributes($entityType, $groupId, Audience\Model::RELATIONSHIP_SELF),
                        $label
                    );

                    $audienceSegment->addLabel($entityType, $codec->encodeValue($groupId), $label);
                    $audienceSegments[] = $audienceSegment;
                }
            }
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'action'               => 'Create',
            'article'              => $article,
            'featureImageZone'     => $zone->getTwigContext(),
            'isAutoSavable'        => !$article->isApproved(),
            'audienceSegments'     => $audienceSegments,
            'availableEntityTypes' => $this->getAvailableAudienceTypeStrategies(),
        ])->setTitle("Create {$this->getDi()->get('sitePage')->getLocalisedTerm('news', false, true)}"));
    }


    /**
     * Create a news article
     *
     * @api   POST /comms/news/create
     * @level 18.1
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
        $article = $this->newsMapper->createModel()->setAuthor($this->getUser());

        $this->postUpdate($article);
    }


    /**
     * Provide form to update a news article
     *
     * @api   GET /comms/news/{articleId}/update
     * @level 18.1
     *
     * @param Model $article
     * @throws InvalidArgumentException
     * @throws MapperException\InsufficientHydrationException
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \UnexpectedValueException
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
            $zone->attachFiles([$article->getFeatureImage()->getHash()]);
        }

        $codec = new Audience\Segment\Codec($this->audienceMapper);

        $translator = new Audience\Segment\Translator(
            $this->databaseInternal,
            $this->strategyFactory
        );
        $translator->buildCache($article);

        // Audiencing selectize data
        $audienceSegments = [];
        foreach ($article->getAudience() as $segment) {
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

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/create.twig', [
            'action'                 => 'Update',
            'article'                => $article,
            'featureImageZone'       => $zone->getTwigContext(),
            'audienceSegments'       => $audienceSegments,
            'isAutoSavable'          => !$article->isApproved(),
            'availableEntityTypes'   => $this->getAvailableAudienceTypeStrategies(),
        ])->setTitle("Update {$article->getTitle()}"));
    }


    /**
     * Update a news article
     *
     * Must not attempt to submit/un-submit/approve/reject an article.
     *
     * It may change the approved subState, eg AwaitingPublication/Published/Expired
     *
     * @api   POST /comms/news/{articleId}
     * @level 18.1
     *
     * @param Model $article
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\InsufficientHydrationException
     * @throws RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     * @throws \DomainException
     */
    public function postUpdate(Model $article): void
    {
        if (!$article->isDraft() && !(new ValidUserCanAudienceTo($this->getUser(), $this->strategyFactory))->isSatisfiedBy($article)) {
            $this->getResponse()->status->set(400);
            $this->getResponse()->content->set($this->buildJsonOutput([
                'success' => false,
                'message' => "Article audience appears to be invalid",
            ]));
            return;
        }

        try {

            $this->persist($article, $this->getRequest()->post->getArrayCopy());

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

                // Send all the possible routes, JS will determine which ones to show where
                $updateRoute = $this->getRouter()->generate('commsNews.postUpdate', $routeParams);
                $submitRoute = $this->getRouteIfAuthorized('commsNewsModeration.postSubmit', $routeParams);
                $approveRoute = false;
                if ($article->canBeApproved()) {
                    $approveRoute = $this->getRouteIfAuthorized('commsNewsModeration.postApprove', $routeParams);
                }

                $this->getResponse()->content->set($this->buildJsonOutput([
                    'success' => true,
                    'url' => $url,
                    'updateRoute' => $updateRoute,
                    'submitRoute' => $submitRoute,
                    'approveRoute' => $approveRoute
                ]));
            } else {

                /* Otherwise, you're the creator and you're editing this thing normally
                 * Go to the detail screen to see the outcome of your changes
                 * and also use that screen to advance to the next step
                 */
                $nextStep = $this->getRouter()->generate('commsNews.getDetail', $routeParams);
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
     * @throws MapperException\InsufficientHydrationException
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     * @throws \DomainException
     */
    private function persist(Model $article, array $data): void
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
            /** @var Storage\Model $featureImage */
            $featureImage = (new CollectorFactory())->build($data['attachment'] ?: [])->getFirst();
            $article->setFeatureImage($featureImage);
        } else {
            $article->setFeatureImage(null);
        }

        $this->newsMapper->save($article);

        // Persist audience segments
        $transactor = new Audience\Segment\Transactor(
            $this->audienceMapper,
            new Audience\Segment\Codec($this->audienceMapper),
            $this->strategyFactory
        );
        $transactor->save($article, $data['audience'] ?? '');
    }


    /**
     * Get a list of available news topics that can be assigned to an article
     *
     * @api GET /comms/news/topics
     * @level 18.1
     */
    public function getTopics(): void
    {
        $this->getResponse()->content->set($this->buildJsonOutput($this->topicMapper->findAll()));
    }


    /**
     * Load a news article so you can read its contents (the detail screen)
     *
     * @api GET /comms/news/{articleId}
     * @level 18.1
     *
     * @param Model $article
     * @throws MapperException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     */
    public function getDetail(Model $article): void
    {
        $moderationEnabled = $this->getConfigLoader()->get(ConfigMapper::_GLOBAL_MODERATION);

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
                'article'            => $article,
                'moderationEnabled'  => $moderationEnabled,
                'isSavedForLater'    => $isSavedForLater,
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
     * Get a list of articles that are audienced to me for homepage component
     *
     * @api GET /comms/news/lists/homepage
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
     * Delete an article
     *
     * @api POST /comms/news/{articleId}/delete
     * @level 18.1
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

            /* If you're a moderator, go back to the Moderation list
             *
             * TODO: Otherwise go...somewhere else? I don't have a component or dashboard yet
             */
            $nextStep = $this->getRouteIfAuthorized('commsNewsModeration.getList') ?: '/';

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
     * Get a list of articles that were audienced to me
     * This is the central piece of the puzzle
     *
     * @api GET /comms/news/list
     * @level 18.1
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
     * @level 18.1
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
    public function getArchive(int $year = null, int $month = null): void
    {

        /* These referrers should be used, even when they're not explicitly provided in the url
         */
        $params = new Querystring($this->getRequest()->query);
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

        $partialData = $this->generatePartial($urlPrefix, $params, $topics, false, $year, $month);

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
            'newsItems' => $partialData
        ];



        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/archive.twig', $data));
    }


    /**
     * Get a list of articles audienced to you
     *
     * @api GET /comms/news
     * @level 18.1
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
    public function getIndex(): void
    {

        /* These referrers should be used, even when they're not explicitly provided in the url
         */
        $params = new Querystring($this->getRequest()->query);
        $params->setReferrer(Statistics\Mapper::REFERRER_INDEX);

        $topics = $this->topicMapper->findAll(['order'=>['sequence'=>'ASC']]);
        $lists = new Moderation\Lists($this->newsMapper);

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
            'slug' => $params->getSlug() ?: 'all',
            'referrer' => $params->getReferrer(),

            /* For configuring the rss button which changes per topic chosen
             */
            'key' => $this->getUser()->getKey(),

            // Vars for the nav sidebar
            'page' => $this->getRequest()->query['page'],
            'moderationCount' => $lists->getNeedModerationCount(),
            'authoredCounts' => [
                'Drafted' => [
                    'count' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateDraft($this->getUser())),
                        'labelClass' => 'unsubmitted'
                ],
                'Awaiting Moderation' => [
                    'count' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateAwaitingModeration($this->getUser())),
                    'labelClass' => 'submitted'
                ],
                'Rejected' => [
                    'count' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateRejected($this->getUser())),
                    'labelClass' => 'submitted-late'
                ],

            ]
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/index.twig', $data));
    }


    /**
     * Index of articles you've authored
     *
     * @api GET /comms/news/me
     *
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function getAuthored($status, $pageNum)
    {
        $params = new Querystring($this->getRequest()->query);
        $pageNum = $params->getPageNum();
        $status = $params->getStatus();
        $lists = new Moderation\Lists($this->newsMapper);

        if (!$pageNum) {
            $pageNum = 1;
        }

        if (!$status) {
            $status = 'All';
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@commsNews/authored.twig', [
            'pageNum' => $pageNum,
            'status' => $status,

            // Vars for the nav sidebar
            'page' => $this->getRequest()->query['page'],
            'moderationCount' => $lists->getNeedModerationCount(),
            'authoredCounts' => [
                'Drafted' => [
                    'count' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateDraft($this->getUser())),
                    'labelClass' => 'unsubmitted'
                ],
                'Awaiting Moderation' => [
                    'count' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateAwaitingModeration($this->getUser())),
                    'labelClass' => 'submitted'
                ],
                'Rejected' => [
                    'count' => $this->newsMapper->countByPredicates($this->newsMapper->getPredicateRejected($this->getUser())),
                    'labelClass' => 'submitted-late'
                ]
            ]
        ]));
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

        $articles = $this->newsMapper->findByColumn('author_id', $this->getUser()->getId(), $args);

        $map = [
            'id',
            'title',
            'featureImage',
            'publishAt' => [
                'long',
                'relativeTime'
            ],
            'topics' => [
                'name',
                'slug',
            ],
            'status',
            'statusDate' => [
                'long',
                'relativeTime'
            ]
        ];

        $articles->reindex();
        $articles = $articles->jsonSerialize(
            new PropertyDecorator($map, new LinkDecorator($this->router, $this->authorizer, $this->getUser()))
        );

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
        $configuredSearchLimit = $this->getConfigLoader()->get('search_type_limit');
        $searchLimit           = null === $configuredSearchLimit ? self::DEFAULT_SEARCH_LIMIT : (int)$configuredSearchLimit;

        $topicData = (new Lists(
            $searchLimit,
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
     * Generate a partial fragment containing articles for homepage
     *
     * @param Querystring $params
     * @return array
     * @throws MapperException
     */
    private function generatePartialForHomepage(Querystring $params)
    {
        $configuredSearchLimit = $this->getConfigLoader()->get('search_type_limit');
        $searchLimit           = null === $configuredSearchLimit ? self::DEFAULT_SEARCH_LIMIT : (int)$configuredSearchLimit;

        $topicData = (new Lists(
            $searchLimit,
            $this->newsMapper,
            $this->getConfigLoader()->get(ConfigMapper::_READ_MORE),
            $this->getConfigLoader()->get(ConfigMapper::_SAVE_FOR_LATER),
            $this->saveForLaterMapper,
            $this->statisticsMapper,
            $this->statisticsCumulativeMapper,
            $this->databaseInternal,
            $this->articleResolver
        ))->findArticlesForHomepage(
            $params,
            $this->getUser()
        );

        return array_merge($topicData, [
            'hidePaginator' => true,
            'compactMode' => $params->getCompactMode(),
            'referrer' => $params->getReferrer(),
            'folderId' => $params->getFolderId(),
            'readonly' => $params->getReadOnlyFlag()
        ]);
    }


    /**
     * Compute the recipients so author can identify
     *
     * @api /comms/news/:articleId/recipients
     * @level 18.1
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
     * @return string[]
     * @throws UnknownEntityTypeException
     * @throws UnexpectedValueException
     */
    private function getAvailableAudienceTypeStrategies(): array
    {
        $availableStrategies = [];
        foreach ($this->strategyFactory->makeAllSimpleStrategies() as $strategy) {
            if ($strategy->canAudienceToEntityType($this->getUser())) {
                $availableStrategies[] = $strategy::getEntityType();
            }
        }
        return $availableStrategies;
    }
}
