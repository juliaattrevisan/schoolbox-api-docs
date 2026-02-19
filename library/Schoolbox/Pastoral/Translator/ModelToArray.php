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
use Aura\Router\Generator;
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
     * @param Generator         $routeGenerator
     * @param Authorizer        $authorizer
     * @param User              $currentUser
     * @param ScrubAdapter      $scrubAdapter
     */
    public function __construct(
        private UserTranslator $userTranslator,
        private CommentTranslator $commentTranslator,
        private StorageTranslator $storageTranslator,
        protected Generator $routeGenerator,
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
        $storageTranslator = $this->storageTranslator;
        return [
            // Standard fields
            'id'                 => $model->getId(),
            'externalId'         => $model->getExternalId(),
            'student'            => $this->translateUser($model->getStudent()),
            'heading'            => $model->getHeading(),
            'type'               => $model->getType()
                ? [
                    'id'        => $model->getType()->getId(),
                    'name'      => $model->getType()->getName(),
                    'colour'    => $model->getType()->getColor()?->getAsRgba(),
                    'icon'      => $model->getType()->getIcon(),
                    'sentiment' => $model->getType()->getSentiment(),
                    'sequence'  => $model->getType()->getSequence(),
                ]
                : null,
            'subType'            => $model->getSubType()
                ? [
                    'id'        => $model->getSubType()->getId(),
                    'name'      => $model->getSubType()->getName(),
                    'colour'    => $model->getSubType()->getColor()?->getAsRgba(),
                    'icon'      => $model->getSubType()->getIcon(),
                    'sentiment' => $model->getSubType()->getSentiment(),
                    'sequence'  => $model->getSubType()->getSequence(),
                ]
                : null,
            'severity'           => $model->getSeverity()
                ? [
                    'id'       => $model->getSeverity()->getId(),
                    'name'     => $model->getSeverity()->getName(),
                    'sequence' => $model->getSeverity()->getSequence(),
                    'color'    => $model->getSeverity()->getColor(),
                ]
                : null,
            'occurredOn'         => $model->getOccurredOn()?->toRfc3339String(),
            'points'             => $model->getPoints(),
            'tags'               => $model->getTags()->map(
                function (Tag $tag) {
                    return [
                        'id'   => $tag->getId(),
                        'name' => $tag->getName(),
                    ];
                }
            ),


            // Text and comments
            'bodyPrivate'        => $this->scrubAdapter->purify($model->getBodyPrivate()),
            'bodyPublic'         => $this->scrubAdapter->purify($model->getBodyPublic()),
            'attachmentsPrivate' => $model->getAttachments()
                                          ->filter(
                                              static function ($attachment) {
                                                  /** @var \Schoolbox\Pastoral\Attachment\Model $attachment */
                                                  return !$attachment->isPublic();
                                              }
                                          )
                                          ->map(
                                              static function ($attachment) use ($storageTranslator) {
                                                  /** @var \Schoolbox\Pastoral\Attachment\Model $attachment */
                                                  return call_user_func($storageTranslator, $attachment->getStorage());
                                              }
                                          ),
            'attachmentsPublic'  => $model->getAttachments()
                                          ->filter(
                                              static function ($attachment) {
                                                  /** @var \Schoolbox\Pastoral\Attachment\Model $attachment */
                                                  return $attachment->isPublic();
                                              }
                                          )
                                          ->map(
                                              static function ($attachment) use ($storageTranslator) {
                                                  /** @var \Schoolbox\Pastoral\Attachment\Model $attachment */
                                                  return call_user_func($storageTranslator, $attachment->getStorage());
                                              }
                                          ),
            'commentsPrivate'    => $model->getComments()->filterBy('private', true)->map($this->commentTranslator),
            'commentsPublic'     => $model->getComments()->filterBy('private', false)->map($this->commentTranslator),
            'comments'           => $model->getComments()->filterBy('private', false)->map($this->commentTranslator),

            // Actions
            'recordActions'      => $model->getRecordActions()->map(
                function (RecordAction $action) {
                    return [
                        'id'        => $action->getAction()->getId(),
                        'name'      => $action->getAction()->getName(),
                        'completed' => $action->getCompleted(),
                        'updatedAt' => $action->getUpdatedAt()->toRfc3339String(),
                        'updatedBy' => $this->translateUser($action->getUpdatedBy()),
                    ];
                }
            ),

            // Excluded for now: access, related records, revisions

            'owner' => $this->translateUser($model->getOwner()),

            // Ownership, last modifying user and time
            'createdBy' => $this->translateUser($model->getOwner()),
            'createdAt' => $model->getRecordDate()->toRfc3339String(),
            'updatedBy' => $this->translateUser($model->getModifiedBy()),
            'updatedAt' => $model->getUpdatedAtDisplay()->toRfc3339String(),

            // Links
            '_links'             => [
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

    /**
     * Adds external id to output of user translator
     * @param User|null $student
     * @return array|null
     */
    private function translateUser(?User $student): array | null
    {
        if (!$student) {
            return null;
        }
        $userArray               = call_user_func($this->userTranslator, $student);
        $userArray['externalId'] = $student->getExternalId();
        return $userArray;
    }
}
