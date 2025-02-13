<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\ClassReport\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Pastoral\Model;
use Schoolbox\Pastoral\RecordAction\Model as RecordAction;
use Schoolbox\Pastoral\Tag\Model as Tag;
use Schoolbox\User;
use Schoolbox\User\Translator\ModelToArray as UserTranslator;

/**
 * Model-to-array translator for the class pastoral report.
 *
 * Notably excludes the following large fields which are not required for the
 * report:
 * * body
 * * attachments
 * * comments
 */
class ModelToArray
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param UserTranslator $userTranslator
     * @param Generator      $routeGenerator
     * @param Authorizer     $authorizer
     * @param User           $currentUser
     */
    public function __construct(
        private UserTranslator $userTranslator,
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
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
            'id'            => $model->getId(),
            'externalId'    => $model->getExternalId(),
            'student'       => $model->getStudent()
                ? call_user_func($this->userTranslator, $model->getStudent())
                : null,
            'heading'       => $model->getHeading(),
            'type'          => $model->getType()
                ? [
                    'id'        => $model->getType()->getId(),
                    'name'      => $model->getType()->getName(),
                    'colour'    => $model->getType()->getColor(),
                    'icon'      => $model->getType()->getIcon(),
                    'sentiment' => $model->getType()->getSentiment(),
                    'sequence'  => $model->getType()->getSequence(),
                ]
                : null,
            'subType'       => $model->getSubType()
                ? [
                    'id'        => $model->getSubType()->getId(),
                    'name'      => $model->getSubType()->getName(),
                    'colour'    => $model->getSubType()->getColor(),
                    'icon'      => $model->getSubType()->getIcon(),
                    'sentiment' => $model->getSubType()->getSentiment(),
                    'sequence'  => $model->getSubType()->getSequence(),
                ]
                : null,
            'severity'      => $model->getSeverity()
                ? [
                    'id'       => $model->getSeverity()->getId(),
                    'name'     => $model->getSeverity()->getName(),
                    'sequence' => $model->getSeverity()->getSequence(),
                    'color'    => $model->getSeverity()->getColor(),
                ]
                : null,
            'points'        => $model->getPoints(),
            'tags'          => $model->getTags()->map(
                function (Tag $tag) {
                    return [
                        'id'   => $tag->getId(),
                        'name' => $tag->getName(),
                    ];
                }
            ),
            // Expiry
            'expiresAt'     => $model->getExpiresAt()?->getDatabaseDate(),
            'isArchived'    => $model->isArchived(),
            // Actions
            'recordActions' => $model->getRecordActions()->map(
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

            // Ownership, last modifying user and time
            'createdBy'     => call_user_func($this->userTranslator, $model->getOwner()),
            'createdAt'     => $model->getCreatedAt()->toRfc3339String(),
            'updatedBy'     => call_user_func($this->userTranslator, $model->getModifiedBy()),
            'updatedAt'     => $model->getUpdatedAt()->toRfc3339String(),

            // Links
            '_links'        => [
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
