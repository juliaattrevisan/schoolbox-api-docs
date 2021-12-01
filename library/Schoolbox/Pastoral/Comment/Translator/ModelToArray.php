<?php
/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Comment\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Alaress\HTMLPurifier\IPurifier;
use Aura\Router\Router;
use Schoolbox\Pastoral\Comment\Model;
use Schoolbox\User;

/**
 * Model-to-array translator for pastoral comments.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * User translator, for translating the comment's owner.
     *
     * @var callable
     */
    private $userTranslator;

    /**
     * HTML purifier adapter, for sanitizing the body of the comment.
     */
    private IPurifier $purifier;

    /**
     * Constructor
     *
     * @param callable   $userTranslator
     * @param Router     $router
     * @param Authorizer $authorizer
     * @param User       $currentUser
     * @param IPurifier  $purifier
     */
    public function __construct(
        callable $userTranslator,
        Router $router,
        Authorizer $authorizer,
        User $currentUser,
        IPurifier $purifier
    ) {
        $this->userTranslator = $userTranslator;
        $this->router         = $router;
        $this->authorizer     = $authorizer;
        $this->currentUser    = $currentUser;
        $this->purifier       = $purifier;
    }

    /**
     * Convert this model to an array.
     *
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        return [
            'id'           => $model->getId(),
            'pastoral'     => [
                'id'      => $model->getPastoral()->getId(),
                'owner'   => call_user_func($this->userTranslator, $model->getPastoral()->getOwner()),
                'student' => call_user_func($this->userTranslator, $model->getPastoral()->getStudent()),
            ],
            'owner'        => call_user_func($this->userTranslator, $model->getOwner()),
            'body'         => $this->purifier->purify($model->getBody()),
            'externalCode' => $model->getExternalCode(),
            'createdAt'    => $model->getCreatedAt() && !$model->getCreatedAt()->isNull()
                ? $model->getCreatedAt()->getUsingFormat(DATE_ATOM)
                : null,
            'updatedAt'    => $model->getUpdatedAt() && !$model->getUpdatedAt()->isNull()
                ? $model->getUpdatedAt()->getUsingFormat(DATE_ATOM)
                : null,
            'isDeleted'    => $model->isDeleted(),
            'private'      => $model->getPrivate(),
            '_links'       => [
                'delete' => $this->getRouteIfAuthorized(
                    'pastoral.postCommentDelete',
                    [
                        'record'    => $model->getPastoral(),
                        'recordId'  => $model->getPastoral()->getId(),
                        'comment'   => $model,
                        'commentId' => $model->getId(),
                    ],
                ),
            ],
        ];
    }
}
