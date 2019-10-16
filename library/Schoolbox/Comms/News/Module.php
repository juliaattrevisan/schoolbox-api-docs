<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Comms\News;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule;
use Alaress\Database\Connector\IConnector;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Comms\News\Audience\UserResolver;
use Schoolbox\Comms\News\Discussion\ThreadLocator;
use Schoolbox\Comms\News\Discussion\Permission\CanModerateThread;
use Schoolbox\Discussion\Thread;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Comms\News\Topic;
use Schoolbox\Permission\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'commsNews';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {

        /* Anyone can spawn new articles, visit the archive or index
         */
        $loggedIn = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn()
            );
        });

        $canCreate = new Rule\LazyCallback(function () {
            /** @var IConnector $db */
            $db = $this->getInjector()->get('databaseInternal');
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_WRITE),
                    new Authorizer\Rule\HasAudiencesAvailable($this->getStrategyFactory()->makeAllSimpleStrategies(), $db)
                )
            );
        });

        /* To see an article, you must be a SU, CommsOfficer, Moderator, or Author - or the article must be published/expired
         * and either publicly visible, or you are in the audience
         */
        $readerPerms = new Rule\LazyCallback(function () {
            /**
             * @var UserResolver $userResolver
             * @var StrategyFactory $strategyFactory
             */
            $userResolver    = $this->getInjector()->get('commsNewsUserResolver');
            $strategyFactory = $this->getInjector()->get('commsNewsAudienceStrategyFactory');
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_WRITE),
                    new Authorizer\Rule\CanModerateAudience($this->getStrategyFactory()),
                    new Authorizer\Rule\IsArticleAuthor(),
                    new Rule\All(
                        new Authorizer\Rule\IsPublishedOrExpired(),
                        new Rule\Any(
                            new Authorizer\Rule\IsShareable(),
                            new Authorizer\Rule\IsMemberOfArticleAudience($userResolver),
                            new Authorizer\Rule\HaveReadPermToAudience($strategyFactory)
                        )
                    )
                )
            );
        });

        // Can I create news in this folder?
        $canCreateInFolder = new Rule\All(
            $loggedIn,
            new Rule\LazyCallback(function () {
                return new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_WRITE),
                    new Rule\HasHomepageAccess(
                        UserAccess::ACCESS_WRITE,
                        function ($params) { return $params['folderId']; },
                        Access\Access::getInstance()
                    )
                );
            })
        );

        // Can modify if any of: Supers, Comms Officers, Article audience moderators, or
        // You are the author, and the article is not yet accepted
        // Note we do not check the user audience to the proposed audience as the audience may be changing
        // eg we need to enable a user to access this route to remove audience segments they no longer have permission to audience to.
        $canModify = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL),
                    new Rule\All(
                        new Rule\Any(
                            new Authorizer\Rule\IsArticleAuthor(),
                            new Authorizer\Rule\InstructorsCanEditOthersArticles($this->getConfig())
                        ),
                        new Authorizer\Rule\CanModerateAudience($this->getStrategyFactory())
                    ),
                    new Rule\All(
                        new Authorizer\Rule\IsArticleAuthor(),
                        new Authorizer\Rule\IsNotYetApproved()
                    )
                )
            );
        });

        // Associating a discussion thread with a comms article:
        // Use the authorizer the discussion thread uses.
        $canAssociate = new Rule\Callback(function ($params, ?User $user) {
            $authorizer = $this->getInjector()->get('authorizer');
            $rule       = new CanModerateThread(
                $authorizer,
                new ThreadLocator(
                    $this->getMapperFactory()->get(Thread\Mapper::class),
                    $this->getMapperFactory()->get(Mapper::class)
                )
            );
            if (!$rule->hasPermission($params['article'], $user)) {
                throw new Unauthorized('User does not have permission to associate a thread with this article');
            }
        });

        $viewRecipients = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL),
                    new Authorizer\Rule\CanModerateAudience($this->getStrategyFactory()),
                    new Authorizer\Rule\IsArticleAuthor()
                )
            );
        });

        $hasHomePageAccess =  new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasHomepageAccess(UserAccess::ACCESS_READ, function ($params) {
                        return $params['folderId'];
                        }, Access\Access::getInstance()
                    )
                )
            );
        });

        $preloadArticle = function ($params) {
            return $this->preloadArticleWithAudience($params);
        };

        $this->register('GET', '/create', 'getCreate', $canCreate);                                              // Create news article
        $this->register('GET', '/create/folder/{folderId:\d+}', 'getCreateInFolder', $canCreateInFolder);
        $this->register('POST', '/create', 'postCreate', $loggedIn);
        $this->register('GET', '/topics', 'getTopics', $loggedIn);                                               // (async) Get a list of topics to chooze
        $this->register('GET', '/{articleId:\d+}/update', 'getUpdate', $canModify, $preloadArticle);             // Update news article
        $this->register('POST', '/{articleId:\d+}', 'postUpdate', $canModify, $preloadArticle);
        $this->register(
            'POST',
            '/{articleId:\d+}/associateWithThread',
            'postAssociateWithDiscussionThread',
            $canAssociate,
            $preloadArticle
        );
        $this->register('GET', '/{articleId:\d+}/recipients', 'getRecipients', $viewRecipients, $preloadArticle);// Find out who will receive this article
        $this->register('POST', '/{articleId:\d+}/delete', 'postDelete', $canModify, $preloadArticle);           // Delete article
        $this->register('GET', '/{articleId:\d+}', 'getDetail', $readerPerms, $preloadArticle);                  // View news article
        $this->register('GET', '/list{/year:\d+,month:[1-9]|1[0-2]}', 'getListByTopic', $loggedIn);              // (async) Get a list of articles audienced to me, by topic
        $this->register('GET', '/archive{/year:\d+,month:[1-9]|1[0-2]}', 'getArchive', $loggedIn);               // News Archive
        $this->register('GET', '', 'getIndex', $loggedIn);                                                       // Index of articles audienced to you
        $this->register('GET', '/lists/folder/{folderId:\d+}', 'getListForHomepage', $hasHomePageAccess);

        // Index of articles authored by the logged in user
        $this->register('GET', '/me', 'getAuthored', $loggedIn);

        // (async) Get a list of articles a user has authored
        $this->register('GET', '/me/articles', 'getAuthoredArticles', $loggedIn);

        // (async) Get a list of articles published to the user
        $this->register('GET', '/lists/feed', 'getListForIndex', $loggedIn);

    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'newsMapper'                 => $this->getMapperFactory()->getLazy(Mapper::class),
            'topicMapper'                => $this->getMapperFactory()->getLazy(Topic\Mapper::class),
            'saveForLaterMapper'         => $this->getMapperFactory()->getLazy(SaveForLater\Mapper::class),
            'statisticsMapper'           => $this->getMapperFactory()->getLazy(Statistics\Mapper::class),
            'statisticsCumulativeMapper' => $this->getMapperFactory()->getLazy(Statistics\Cumulative\Mapper::class),
            'audienceMapper'             => $this->getMapperFactory()->getLazy(Audience\Mapper::class),
            'folderMapper'               => $this->getMapperFactory()->getLazy(Folder\Mapper::class),
            'databaseInternal'           => $injector->lazyGet('databaseInternal'),
            'articleResolver'            => $injector->lazyGet('commsNewsArticleResolver'),
            'userResolver'               => $injector->lazyGet('commsNewsUserResolver'),
            'strategyFactory'            => $injector->lazyGet('commsNewsAudienceStrategyFactory'),
            'threadMapper'               => $this->getMapperFactory()->getLazy(Thread\Mapper::class),
            'errorMapper'                => $this->getMapperFactory()->getLazy(ErrorMapper::class),
        ];
    }


    /**
     * Preload the news article
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws InvalidArgumentException
     * @throws NotFound
     */
    private function preloadArticle(array $params): array
    {
        if (\array_key_exists('article', $params)) {
            $article = $params['article'];
        } else {
            try {
                $article = $this->getArticleMapper()->findById($params['articleId']);
            } catch (MapperException $e) {
                throw new NotFound('Unknown news article.', 0, $e);
            }
        }
        return [
            'article' => $article,
        ];
    }


    /**
     * Preload the news article with audience hydrated
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws InvalidArgumentException
     * @throws NotFound
     * @throws \DomainException
     */
    private function preloadArticleWithAudience(array $params): array
    {
        $args = $this->preloadArticle($params);
        /** @var Model $article */
        $article = $args['article'];
        $article->setAudience($this->getAudienceMapper()->findForArticles([$article->getId()]));
        return $args;
    }


    /**
     * @return \Schoolbox\Mapper|Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    private function getArticleMapper(): Mapper
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Audience\Mapper
     * @throws InvalidArgumentException
     */
    private function getAudienceMapper(): Audience\Mapper
    {
        return $this->getMapperFactory()->get(Audience\Mapper::class);
    }


    /**
     * @return StrategyFactory|object
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function getStrategyFactory(): StrategyFactory
    {
        return $this->injector->get('commsNewsAudienceStrategyFactory');
    }
}
