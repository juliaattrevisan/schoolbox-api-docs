<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Moderation;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Closure;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Role;

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
            '/{evidenceId}/publish',
            'putPublish',
            $staffOnlyRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        /* Reject a Learning Moment
         */
        $this->register(
            self::METHOD_PUT,
            '/{evidenceId}/reject',
            'postReject',
            $staffOnlyRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
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
            ->get(\Schoolbox\Learning\Moment\Evidence\Mapper::class)
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
            $this->getMapperFactory()->get(\Schoolbox\Learning\Moment\Evidence\Mapper::class),
            $this->getMapperFactory()->get(CommentMapper::class),
        );
    }


//    /**
//     * {@inheritdoc}
//     */
//    public function initTemplatePaths(IChainLoader $templateLoader): void
//    {
//        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
//    }
}
