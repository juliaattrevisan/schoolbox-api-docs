<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\ReportBuilder\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Aura\Router\Generator;
use Schoolbox\Pastoral\Comment\Translator\ModelToArray as CommentTranslator;
use Schoolbox\Pastoral\Model;
use Schoolbox\Pastoral\Tag\Model as Tag;
use Schoolbox\Resource\Storage\Translator\ModelToArray as StorageTranslator;
use Schoolbox\User;
use Schoolbox\User\Translator\ModelToArray as UserTranslator;

class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        private UserTranslator $userTranslator,
        private CommentTranslator $commentTranslator,
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private ScrubAdapter $scrubAdapter,
    ) {}

    public function __invoke(Model $model): array
    {
        return [
            'id'         => $model->getId(),
            'externalId' => $model->getExternalId(),
            'student'    => $this->translateUser($model->getStudent()),
            'isArchived' => $model->isArchived(),
            'heading'    => $model->getHeading(),
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
            // Ownership, last modifying user and time
            'owner' => $this->translateUser($model->getOwner()),
            'createdAt' => $model->getRecordDate(),
            'updatedBy' => $this->translateUser($model->getModifiedBy()),
            'updatedAt' => $model->getUpdatedAtDisplay(),

            'uncompletedActionCount' => $model->getUncompletedActionCount(),

            // Text and comments
            'bodyPublic'         => $this->scrubAdapter->purify($model->getBodyPublic()),

            'comments'            => $model->getComments()->map($this->commentTranslator)
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
