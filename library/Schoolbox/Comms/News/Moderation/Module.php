<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Moderation;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Comms\News\Authorizer\Rule\CanModerateAudience;
use Schoolbox\Comms\News\Authorizer\Rule\IsModerationEnabled;
use Schoolbox\Comms\News\Authorizer\Rule\IsArticleAuthor;
use Schoolbox\Comms\News;
use Schoolbox\Error;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;

class Module extends BaseModule
{

    /**
     * @inheritdoc
     */
    protected $basename = 'commsNewsModeration';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        $suOrCommsOfficer = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL)
            )
        );

        // loggedIn && (SU || CO || moderator || (moderationEnabled && author))
        $canSubmit = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL),
                    new CanModerateAudience($this->getStrategyFactory()),
                    new Rule\All(
                        new IsModerationEnabled($this->getConfig()),
                        new IsArticleAuthor()
                    )
                )
            );
        });



        // loggedIn && (SU || CO || moderator)
        $canApprove = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_NEWS, UserAccess::ACCESS_FULL),
                    new CanModerateAudience($this->getStrategyFactory())
                )
            );
        });


        // Reject rule: same as approve rule
        // This allows any user who may submit an article to reject
        // (a.k.a. unpublish) it, but grants the article author no special
        // permission to reject news which they have created.
        $canReject = $canApprove;

        $preloadArticle = function ($params) {
            return $this->preloadArticleWithAudience($params);
        };

        $this->register('GET', '', 'getList', $suOrCommsOfficer);                                         // See all items requiring moderation
        $this->register('POST', '/{articleId:\d+}/submit', 'postSubmit', $canSubmit, $preloadArticle);    // Submit For Moderation
        $this->register('POST', '/{articleId:\d+}/accept', 'postApprove', $canApprove, $preloadArticle);  // Approve
        $this->register('POST', '/{articleId:\d+}/reject', 'postReject', $canReject, $preloadArticle);    // Reject
    }


    /**
     * Preload the news article
     *
     * @param array $params
     * @return array
     * @throws NotFound
     * @throws InvalidArgumentException
     */
    private function preloadNews(array $params): array
    {
        if (\array_key_exists('article', $params)) {
            $article = $params['article'];
        } else {
            try {
                $article = $this->getArticleMapper()->findById($params['articleId']);
            } catch (MapperException $e) {
                throw new NotFound($this->getErrorMapper()->getMessageById(Error\Mapper::ERROR_GENERIC_NOT_FOUND), 0, $e);
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
        $args = $this->preloadNews($params);
        /** @var News\Model $article */
        $article = $args['article'];
        $article->setAudience(News\Audience\Mapper::getInstance()->findForArticles([$article->getId()]));
        return $args;
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
            'newsMapper'       => $this->getMapperFactory()->getLazy(News\Mapper::class),
            'validSpecFactory' => $injector->lazy(function () {
                return new News\Validation\Factory($this->getStrategyFactory());
            }),
        ];
    }


    /**
     * @return Mapper|Error\Mapper
     * @throws InvalidArgumentException
     */
    private function getErrorMapper(): Error\Mapper
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }


    /**
     * @return Mapper|News\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    private function getArticleMapper(): News\Mapper
    {
        return $this->getMapperFactory()->get(News\Mapper::class);
    }


    /**
     * @return News\Audience\Segment\StrategyFactory|object
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    private function getStrategyFactory(): News\Audience\Segment\StrategyFactory
    {
        return $this->injector->get('commsNewsAudienceStrategyFactory');
    }
}
