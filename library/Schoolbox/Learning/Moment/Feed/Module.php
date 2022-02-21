<?php

/**
*
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Feed;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Closure;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
use Schoolbox\Learning\Badge\Award\Mapper as BadgeAwardMapper;
use Schoolbox\Learning\Moment\Evidence\Mapper as EvidenceMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningMomentFeed';


    /**
     * @inheritdoc
     */
    protected function registerRoutes()
    {
        // Rule for viewing a user's feed:
        // * Staff and superusers may view anyone's
        // * Students may view their own
        // * Parents may view their own and their kid's
        $feedPermissions =  new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsUserOrParentOfUser(
                        $this->getExternalUser(),
                        UserMapper::getInstance(),
                        'userId'
                    ),
                ),
            );
        });
        $feedPreload = Closure::fromCallable([$this, 'preloadUser']);

        $this->register(
            self::METHOD_GET,
            '/user/{userId}',
            'getFeedForUser',
            $feedPermissions,
            $feedPreload,
        );
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(EvidenceMapper::class),
            $this->getMapperFactory()->get(BadgeAwardMapper::class),
            $this->getMapperFactory()->get(CommentMapper::class),
            $this->injector->get('databaseInternal'),
        );
    }


    /**
     * Preload the requested student
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function preloadUser(array $params): array
    {
        return [
            'user' => $this->getMapperFactory()
                           ->get(UserMapper::class)
                           ->findById($params['userId']),
        ];
    }
}
