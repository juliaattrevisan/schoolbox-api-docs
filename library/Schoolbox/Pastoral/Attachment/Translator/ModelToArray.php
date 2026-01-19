<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Attachment\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Alaress\HTMLPurifier\IPurifier;
use Aura\Router\Generator;
use Schoolbox\Pastoral\Attachment\Model;
use Schoolbox\User;

/**
 * Model-to-array translator for pastoral attachments.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * User translator, for translating the attachments's owner.
     *
     * @var callable
     */
    private $userTranslator;

    /**
     * Constructor
     *
     * @param callable   $userTranslator
     * @param Generator  $routeGenerator
     * @param Authorizer $authorizer
     * @param User       $currentUser
     * @param IPurifier  $purifier
     */
    public function __construct(
        callable $userTranslator,
        Generator $routeGenerator,
        Authorizer $authorizer,
        User $currentUser,
    ) {
        $this->userTranslator = $userTranslator;
        $this->routeGenerator = $routeGenerator;
        $this->authorizer     = $authorizer;
        $this->currentUser    = $currentUser;
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
            'id' => $model->getId(),
            'realname' => $model->getRealname(),
            // 'pastoral'     => [
            //     'id'      => $model->getPastoral()->getId(),
            //     'owner'   => call_user_func($this->userTranslator, $model->getPastoral()->getOwner()),
            //     'student' => call_user_func($this->userTranslator, $model->getPastoral()->getStudent()),
            // ],
            // 'owner'        => call_user_func($this->userTranslator, $model->getOwner()),
            // 'body'         => $this->purifier->purify($model->getBody()),
            // 'externalCode' => $model->getExternalCode(),
            // 'createdAt'    => $model->getCreatedAt()->getUsingFormat(DATE_ATOM),
            // 'updatedAt'    => $model->getUpdatedAt()->getUsingFormat(DATE_ATOM),
            // 'isDeleted'    => $model->isDeleted(),
            // 'private'      => $model->getPrivate(),
            // '_links'       => [
            //     'delete' => $this->getRouteIfAuthorized(
            //         'pastoral.postCommentDelete',
            //         [
            //             'record'    => $model->getPastoral(),
            //             'recordId'  => $model->getPastoral()->getId(),
            //             'comment'   => $model,
            //             'commentId' => $model->getId(),
            //         ],
            //     ),
            // ],
        ];
    }
}
