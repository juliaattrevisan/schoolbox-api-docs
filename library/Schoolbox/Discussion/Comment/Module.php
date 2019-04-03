<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Comment;

use Alaress\Authorizer as Auth;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Comment\Authorizer\IsCommentAuthor;
use Schoolbox\Discussion\Thread;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Module for discussion comment requests.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'discussionComments';


    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        /** @var Auth\Authorizer $authorizer */
        /** @var News\Mapper     $newsMapper */
        /** @var Thread\Mapper   $threadMapper */
        $authorizer       = $this->getInjector()->get('authorizer');
        $newsMapper       = $this->getMapperFactory()->get(News\Mapper::class);
        $threadMapper     = $this->getMapperFactory()->get(Thread\Mapper::class);
        $commsContext     = new News\Discussion\Context($threadMapper, $newsMapper);
        $deleteAuthorizer = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new IsCommentAuthor(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $commsContext) {
                return new Thread\Authorizer\CanActUponThread(
                    new News\Discussion\Permission\CanModerate($authorizer, $commsContext)
                );
            })
        );
        $getAuthorizer    = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new IsCommentAuthor(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $commsContext) {
                return new Thread\Authorizer\CanActUponThread(
                    new News\Discussion\Permission\CanGet($authorizer, $commsContext)
                );
            })
        );

        $postAuthorizer    = new Auth\Rule\Any(
            new Auth\Rule\IsSuperuser(),
            new Auth\Rule\LazyCallback(function () use ($authorizer, $newsMapper) {
                return new Thread\Authorizer\CanPostIntoThread($authorizer, $newsMapper);
            })
        );

        // Routes
        $this->register(
            'DELETE',
            '/{id:\d+}',
            'deleteComment',
            $deleteAuthorizer,
            [$this, 'preloadCommentAllowDeleted']
        );
        $this->register(
            'GET',
            '/{id:\d+}/comments',
            'getCommentsByParent',
            $getAuthorizer,
            [$this, 'preloadComment']
        );
        $this->register(
            'POST',
            '/{id:\d+}/comments',
            'createCommentInThread',
            $postAuthorizer
        );
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'mapper'       => $this->getMapperFactory()->getLazy(Mapper::class),
            'threadMapper' => $this->getMapperFactory()->getLazy(Thread\Mapper::class),
        ];
    }


    /**
     * Preload the requested comment.
     *
     * @param array $params
     * @return array
     * @throws MapperException         if the comment never existed
     * @throws MapperException\Deleted if the comment has been deleted
     */
    public function preloadComment(array $params): array
    {
        return [
            'comment' => $this->getMapperFactory()->get(Mapper::class)->findById($params['id']),
        ];
    }


    /**
     * Preload the requested comment. If the comment was deleted, it is still
     * preloaded.
     *
     * @param array $params
     * @return array
     * @throws MapperException if the comment never existed
     */
    public function preloadCommentAllowDeleted(array $params): array
    {
        try {
            return $this->preloadComment($params);
        } catch (MapperException\Deleted $e) {
            return ['comment' => $e->getDeletedObject(),];
        }
    }
}
