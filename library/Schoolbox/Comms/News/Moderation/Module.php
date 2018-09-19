<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
use Schoolbox\Comms\News\Authorizer\Rule\StateCanBeSubmitted;
use Schoolbox\Comms\News\Authorizer\Rule\StateCanBeApproved;
use Schoolbox\Comms\News\Authorizer\Rule\StateCanBeRejected;
use Schoolbox\Comms\News\Authorizer\Rule\IsArticleAuthor;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Policy;
use Schoolbox\Error;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;

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
                new Rule\HasServicePermission(UserAccess::SERVICE_COMMS_OFFICER, UserAccess::ACCESS_FULL)
            )
        );

        // loggedIn && canBeSubmitted && (SU || CO || moderator || (moderationEnabled && author))
        $canSubmit = new Rule\All(
            new Rule\IsLoggedIn(),
            new StateCanBeSubmitted(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_COMMS_OFFICER, UserAccess::ACCESS_FULL),
                new CanModerateAudience($this->getFolderMapper()),
                new Rule\All(
                    new IsModerationEnabled($this->getPolicyMapper()),
                    new IsArticleAuthor()
                )
            )
        );

        // loggedIn && canBeApproved && (SU || CO || moderator)
        $canApprove = new Rule\All(
            new Rule\IsLoggedIn(),
            new StateCanBeApproved(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_COMMS_OFFICER, UserAccess::ACCESS_FULL),
                new CanModerateAudience($this->getFolderMapper())
            )
        );

        // loggedIn && canBeRejected && (SU || CO || moderator)
        $canReject = new Rule\All(
            new Rule\IsLoggedIn(),
            new StateCanBeRejected(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_COMMS_OFFICER, UserAccess::ACCESS_FULL),
                new CanModerateAudience($this->getFolderMapper())
            )
        );

        $preloadNews = function ($params) {
            return $this->preloadNews($params);
        };

        $this->register('GET', '', 'getList', $suOrCommsOfficer);                                      // See all items requiring moderation
        $this->register('POST', '/{articleId:\d+}/submit', 'postSubmit', $canSubmit, $preloadNews);    // Submit For Moderation
        $this->register('POST', '/{articleId:\d+}/accept', 'postApprove', $canApprove, $preloadNews);  // Approve
        $this->register('POST', '/{articleId:\d+}/reject', 'postReject', $canReject, $preloadNews);    // Reject
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
                throw new NotFound($this->getErrorMapper()->getMessageById(Error\Mapper::ERROR_GENERIC_NOT_FOUND));
            }
        }

        return [
            'article' => $article,
        ];
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
            'newsMapper'   => $this->getMapperFactory()->getLazy(News\Mapper::class),
            'policyMapper' => $this->getMapperFactory()->getLazy(Policy\Mapper::class),
        ];
    }


    /**
     * @return Mapper|Error\Mapper
     * @throws InvalidArgumentException
     */
    private function getErrorMapper()
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }


    /**
     * @return Mapper|News\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    private function getArticleMapper()
    {
        return $this->getMapperFactory()->get(News\Mapper::class);
    }


    /**
     * @return Mapper|Folder\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    private function getFolderMapper(): Folder\Mapper
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }


    /**
     * @return Mapper|Policy\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    private function getPolicyMapper(): Policy\Mapper
    {
        return $this->getMapperFactory()->get(Policy\Mapper::class);
    }
}
