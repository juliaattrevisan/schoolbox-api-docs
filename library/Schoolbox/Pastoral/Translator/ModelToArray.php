<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Aura\Router\Router;
use Schoolbox\Pastoral\Comment\Translator\ModelToArray as CommentTranslator;
use Schoolbox\Pastoral\Model;
use Schoolbox\Pastoral\RecordAction\Model as RecordAction;
use Schoolbox\Pastoral\Tag\Model as Tag;
use Schoolbox\Resource\Storage\Translator\ModelToArray as StorageTranslator;
use Schoolbox\User;
use Schoolbox\User\Translator\ModelToArray as UserTranslator;

/**
 * Translator for `\Schoolbox\Pastoral\Model` instances.
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param UserTranslator    $userTranslator
     * @param CommentTranslator $commentTranslator
     * @param StorageTranslator $storageTranslator
     * @param Router            $router
     * @param Authorizer        $authorizer
     * @param User              $currentUser
     * @param ScrubAdapter      $scrubAdapter
     */
    public function __construct(
        private UserTranslator $userTranslator,
        private CommentTranslator $commentTranslator,
        private StorageTranslator $storageTranslator,
        protected Router $router,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private ScrubAdapter $scrubAdapter,
    ) {
    }

    /**
     * Translates a `\Schoolbox\Pastoral\Model` instance to an array representation.
     *
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        return [
            // Standard fields
            'id'                     => $model->getId(),
            'externalId'             => $model->getExternalId(),
            'student'                => $model->getStudent()
                ? call_user_func($this->userTranslator, $model->getStudent())
                : null,
            'heading'                => $model->getHeading(),
            'type'                   => $model->getType()
                ? [
                    'id'        => $model->getType()->getId(),
                    'name'      => $model->getType()->getName(),
                    'colour'    => $model->getType()->getColor(),
                    'icon'      => $model->getType()->getIcon(),
                    'sentiment' => $model->getType()->getSentiment(),
                ]
                : null,
            'subType'                => $model->getSubType()
                ? [
                    'id'        => $model->getSubType()->getId(),
                    'name'      => $model->getSubType()->getName(),
                    'colour'    => $model->getSubType()->getColor(),
                    'icon'      => $model->getSubType()->getIcon(),
                    'sentiment' => $model->getSubType()->getSentiment(),
                ]
                : null,
            'severity'               => $model->getSeverity()
                ? [
                    'id'   => $model->getSeverity()->getId(),
                    'name' => $model->getSeverity()->getName(),
                ]
                : null,
            'points'                 => $model->getPoints(),
            'tags'                   => $model->getTags()->map(
                function (Tag $tag) {
                    return [
                        'id'   => $tag->getId(),
                        'name' => $tag->getName(),
                    ];
                }
            ),
            'studentCommentsAllowed' => $model->getStudentCommentsAllowed(),
            'parentCommentsAllowed'  => $model->getParentCommentsAllowed(),

            // Text and comments
            'bodyPrivate'            => $this->scrubAdapter->purify($model->getBodyPrivate()),
            'bodyPublic'             => $this->scrubAdapter->purify($model->getBodyPublic()),
            'attachments'            => $model->getAttachments()->map($this->storageTranslator),
            'comments'               => $model->getComments()->map($this->commentTranslator),

            // Actions
            'recordActions'          => $model->getRecordActions()->map(
                function (RecordAction $action) {
                    return [
                        'id'        => $action->getAction()->getId(),
                        'name'      => $action->getAction()->getName(),
                        'completed' => $action->getCompleted(),
                        'updatedAt' => $action->getUpdatedAt()->toRfc3339String(),
                        'updatedBy' => $action->getUpdatedBy()
                            ? call_user_func($this->userTranslator, $action->getUpdatedBy())
                            : null,
                    ];
                }
            ),

            // Excluded for now: access, related records, revisions

            // Ownership, last modifying user and time
            'createdBy'              => call_user_func($this->userTranslator, $model->getOwner()),
            'createdAt'              => $model->getCreatedAt()->toRfc3339String(),
            'updatedBy'              => call_user_func($this->userTranslator, $model->getModifiedByDisplay()),
            'updatedAt'              => $model->getUpdatedAtDisplay()->toRfc3339String(),

            // Links
            '_links'                 => [
                'read' => $this->getRouteIfAuthorized(
                    'pastoral.getStudentRecord',
                    [
                        'student'   => $model->getStudent(),
                        'studentId' => $model->getStudent()->getId(),
                        'record'    => $model,
                        'recordId'  => $model->getId(),
                    ],
                ),
            ],
        ];
    }
}
