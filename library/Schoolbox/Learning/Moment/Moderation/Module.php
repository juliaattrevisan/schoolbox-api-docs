<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Moderation;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Closure;
use Schoolbox\User;
use Schoolbox\Role;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningMomentModeration';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Staff only, for moderation workflow
         * Staff members must have the Learning Moments role permission though.
         */
        $staffOnlyRule = new Rule\LazyCallback(function () {
            return new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRolePermission(Role::PERMISSION_ACCESS_EVIDENCE, Role::STAFF),
            );
        });

        /* Publish a Learning Moment
         */
        $this->register(
            self::METHOD_PUT,
            '/{evidenceId:\d+}/publish',
            'putPublish',
            new Rule\All(
                $staffOnlyRule,
                $this->buildRuleCanProgressToState('publish'),
            ),
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        /* Bulk Publish a set of learning moments (typically from Cohort Overview)
         * TODO - check that this is okay to bypass buildRuleCanProgressToState()
         */
        $this->register(
            self::METHOD_PUT,
            '/bulk/publish',
            'putBulkPublish',
            $staffOnlyRule,
        );

        /* Reject a Learning Moment
         */
        $this->register(
            self::METHOD_PUT,
            '/{evidenceId:\d+}/reject',
            'putReject',
            new Rule\All(
                $staffOnlyRule,
                $this->buildRuleCanProgressToState('reject'),
            ),
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        /* Bulk reject a set of learning moments (typically from Cohort Overview)
         * TODO - check that this is okay to bypass buildRuleCanProgressToState()
         */
        $this->register(
            self::METHOD_PUT,
            '/bulk/reject',
            'putBulkReject',
            $staffOnlyRule,
        );
    }


    /**
     * Preload the requested moment evidence
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     * @throws \InvalidArgumentException
     */
    private function preloadEvidence(array $params): array
    {
        $evidence = $this->getMapperFactory()
            ->get(\Schoolbox\Learning\Moment\Mapper::class)
            ->findById($params['evidenceId']);

        return [
            'userId' => $evidence->getRecipient()->getId(),
            'creatorId' => $evidence->getCreator()->getId(),
            'evidence' => $evidence
        ];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(\Schoolbox\Learning\Moment\Mapper::class),
            $this->getMapperFactory()->get(CommentMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Discussion\Thread\Mapper::class),
        );
    }


//    /**
//     * {@inheritdoc}
//     */
//    public function initTemplatePaths(IChainLoader $templateLoader): void
//    {
//        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
//    }


    /**
     * Authorizes that a particular evidence can be progressed to a given state
     * TODO dedupe this with the same thing in library/Schoolbox/Learning/Moment/Module.php, we have two spots for moderation...
     *
     * @param string $state
     */
    private function buildRuleCanProgressToState(string $state): Rule\IAuthorizationRule
    {
        return new Rule\LazyCallback(
            fn() => new Rule\Callback(function (array $params, User $user) use ($state): void {
                // Because preload exceptions are not handled immediately, we can't fatal if the preload failed.
                if (!($params['evidence'] ?? null) instanceof \Schoolbox\Learning\Moment\Model) {
                    throw new InvalidPath("No evidence found in route params");
                }

                /** @var \Schoolbox\Learning\Moment\Model $evidence */
                $evidence = $params['evidence'];

                // NextRoutes is the source of truth of what the next possible states are
                $nextRoutes = (new \Schoolbox\Learning\Moment\NextRoutes())->generate(
                    $evidence,
                    $user,
                    $this->getConfig()->get(ConfigMapper::NAME_LEARNING_MOMENTS_MODERATION_ENABLED)
                );

                if (empty($nextRoutes[$state])) {
                    throw new Forbidden("Cannot progress to state '$state'");
                }
            }),
        );
    }
}
